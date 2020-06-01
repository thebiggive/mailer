<?php

declare(strict_types=1);

namespace Mailer\Application\Messenger\Handler;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\Validator;
use Psr\Log\LoggerInterface;
use Swift_Image;
use Swift_Mailer;
use Swift_Message;
use Swift_SwiftException;
use Swift_TransportException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Twig;

/**
 * Takes `SendRequest`s off the SQS/Redis queue and (tries to) send emails.
 */
class SendRequestConsumer implements MessageHandlerInterface
{
    private string $appEnv;
    private Config $config;
    private LoggerInterface $logger;
    private Swift_Mailer $mailer;
    private Twig\Environment $twig;
    private Validator\SendRequest $validator;

    public function __construct(
        string $appEnv,
        Config $configLoader,
        LoggerInterface $logger,
        Swift_Mailer $mailer,
        Twig\Environment $twig,
        Validator\SendRequest $validator
    ) {
        $this->appEnv = $appEnv;
        $this->config = $configLoader;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->validator = $validator;
    }

    /**
     * @param SendRequest $sendRequest
     * @return bool Whether sending succeeded
     */
    public function __invoke(SendRequest $sendRequest): void
    {
        // Config can change over time and roll out to the API & consumers at slightly different times, so we should
        // re-validate our `SendRequest`'s params before sending.
        if (!$this->validator->validate($sendRequest, true)) {
            // We don't treat this as permanent (`UnrecoverableExceptionInterface`) in case a change is rolling out and
            // the message could become valid with an imminent consumer update.
            $this->fail($sendRequest->id, "Validation failed: {$this->validator->getReason()}");
        }

        $email = new Swift_Message();

        $additionalParams['headerImageRef'] = $this->embedImages($email, 'TBG.png');
        $additionalParams['footerImageRef'] = $this->embedImages($email, 'CCh.png');
        $additionalParams['renderHtml'] = true;

        $templateMergeParams = array_merge($additionalParams, $sendRequest->params);

        $bodyRenderedHtml = $this->twig->render("{$sendRequest->templateKey}.html.twig", $templateMergeParams);
        $bodyPlainText = strip_tags($this->twig->render("{$sendRequest->templateKey}.html.twig", $sendRequest->params));

        $config = $this->config->get($sendRequest->templateKey);

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(fn($param) => $sendRequest->params[$param], $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        if ($this->appEnv !== 'production') {
            $subject = "({$this->appEnv}) $subject";
        }

        $email->addTo($sendRequest->recipientEmailAddress)
            ->setSubject($subject)
            ->setBody($bodyRenderedHtml)
            ->addPart($bodyPlainText, 'text/plain')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom(getenv('SENDER_ADDRESS'));

        try {
            $numberOfRecipients = $this->mailer->send($email);

            if ($numberOfRecipients === 0) {
                $this->fail($sendRequest->id, 'Email send reached no recipients');
            }
        } catch (Swift_TransportException $exception) {
            // It's very likely that the long running process's connection timed out. We don't disconnect after
            // every send because we ideally want to benefit from the connection sharing when we *do* have a lot
            // of mails to process in a few seconds. So the best thing to do for the high- and low-volume case is
            // to catch this error, and when it happens re-connect before proceeding with the send.
            // See https://stackoverflow.com/a/22629213/2803757
            $this->mailer->getTransport()->stop();
            $this->logger->info("Reset connection for ID {$sendRequest->id} following '{$exception->getMessage()}'");

            $numberOfRecipients = $this->mailer->send($email);

            if ($numberOfRecipients === 0) {
                $this->fail($sendRequest->id, 'Email send reached no recipients on post-transport-error retry');
            }
        } catch (Swift_SwiftException $exception) {
            // SwiftMailer transports can bail out with exceptions e.g. on connection timeouts.
            $class = get_class($exception);
            $this->fail($sendRequest->id, "Email send failed. $class: {$exception->getMessage()}");
        }

        $this->logger->info("Sent ID {$sendRequest->id}");
    }

    /**
     * @param string $id
     * @param string $reason
     * @throws RuntimeException
     */
    private function fail(string $id, string $reason): void
    {
        $this->logger->error("Sending ID $id failed: $reason");
        throw new RuntimeException($reason);
    }

    /**
     * @param Swift_Message $email
     * @param string $fileName
     * @return string Path-like reference to embedded image, for use in other parts' HTML.
     */
    private function embedImages(Swift_Message $email, string $fileName): string
    {
        $pathToImages = dirname(__DIR__, 4) . '/images/';

        return $email->embed(Swift_Image::fromPath($pathToImages . $fileName));
    }
}

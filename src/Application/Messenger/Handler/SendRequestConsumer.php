<?php

declare(strict_types=1);

namespace Mailer\Application\Messenger\Handler;

use JetBrains\PhpStorm\Pure;
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
    #[Pure]
    public function __construct(private string $appEnv, private Config $configLoader, private LoggerInterface $logger, private Swift_Mailer $mailer, private Twig\Environment $twig, private Validator\SendRequest $validator)
    {
    }

    /**
     * @param SendRequest $sendRequest
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

        $this->logger->info("Processing ID {$sendRequest->id}...");

        $email = new Swift_Message();

        $additionalParams['headerImageRef'] = $this->embedImages($email, 'TBG.png');
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

        // Deal with the fact that SwiftMailer doesn't have a mechanism to fix stale SMTP connections when
        // working with long-lived consumers. This lets us keep a live connection but guarantee it's going to
        // be awake for the coming send. See https://stackoverflow.com/a/22629213/2803757
        if (!$this->mailer->getTransport()->ping()) {
            $this->mailer->getTransport()->stop();
            try {
                $this->mailer->getTransport()->start();
            } catch (Swift_TransportException $exception) {
                $this->fail(
                    $sendRequest->id,
                    sprintf('transport start %s: %s', get_class($exception), $exception->getMessage()),
                );
            }
        }

        try {
            $numberOfRecipients = $this->mailer->send($email);

            if ($numberOfRecipients === 0) {
                $this->fail($sendRequest->id, 'Email send reached no recipients');
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

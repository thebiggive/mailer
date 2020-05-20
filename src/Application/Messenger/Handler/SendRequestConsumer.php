<?php

declare(strict_types=1);

namespace Mailer\Application\Messenger\Handler;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\Validator;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_SwiftException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Openbuildings\Swiftmailer\CssInlinerPlugin;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
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

        // Instantiate a new Swift Message object
        $email = new \Swift_Message();
        $cssToInlineStyles  = new CssToInlineStyles();

        $images['headerImageRef'] = $this->embedImages($email, 'TBG.jpg');
        $images['footerImageRef'] = $this->embedImages($email, 'CCh.jpg');

        $templateMergeParams = array_merge($images, $sendRequest->params);

        $bodyRenderedHtml = $this->twig->render("{$sendRequest->templateKey}.html.twig", $templateMergeParams);
        $bodyPlainText = strip_tags($bodyRenderedHtml);

        $cssToInlineStyles->setUseInlineStylesBlock(false)
            ->convert(
                $bodyRenderedHtml,
                file_get_contents(dirname(__DIR__, 4) . '/templates/style.css')
            );

        $config = $this->config->get($sendRequest->templateKey);

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(fn($param) => $sendRequest->params[$param], $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        if ($this->appEnv !== 'production') {
            $subject = "({$this->appEnv}) $subject";
        }

        $email->addTo($sendRequest->recipientEmailAddress)
            ->setSubject($subject)
            ->setBody($cssToInlineStyles)
            ->addPart($bodyPlainText, 'text/plain')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom(getenv('SENDER_ADDRESS')
            ->registerPlugin(new CssInlinerPlugin($cssToInlineStyles)));

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

    private function fail(string $id, string $reason)
    {
        $this->logger->error("Sending ID $id failed: $reason");
        throw new RuntimeException($reason);
    }

    private function embedImages($email, $fileName)
    {
        $pathToImages = dirname(__DIR__, 4) . '/images/';
        return $email->embed(\Swift_Image::fromPath($pathToImages . $fileName));
    }
}

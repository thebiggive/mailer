<?php

declare(strict_types=1);

namespace Mailer\Application\Messenger\Handler;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\Validator;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Twig;

/**
 * Takes `SendRequest`s off the Redis queue and (tries to) send emails.
 */
class SendRequestConsumer implements MessageHandlerInterface
{
    private Config $config;
    private LoggerInterface $logger;
    private Swift_Mailer $mailer;
    private Twig\Environment $twig;
    private Validator\SendRequest $validator;

    public function __construct(
        Config $configLoader,
        LoggerInterface $logger,
        Swift_Mailer $mailer,
        Twig\Environment $twig,
        Validator\SendRequest $validator
    ) {
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

        $bodyRenderedHtml = $this->twig->render("{$sendRequest->templateKey}.html.twig", $sendRequest->params);
        $bodyPlainText = strip_tags($bodyRenderedHtml);

        $config = $this->config->get($sendRequest->templateKey);

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(fn($param) => $sendRequest->params[$param], $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        $email = (new \Swift_Message())
            ->addTo($sendRequest->recipientEmailAddress)
            ->setSubject($subject)
            ->setBody($bodyPlainText)
            ->addPart($bodyRenderedHtml, 'text/html')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom(getenv('SENDER_ADDRESS'));

        $numberOfRecipients = $this->mailer->send($email);

        if ($numberOfRecipients === 0) {
            $this->fail($sendRequest->id, 'Email send failed');
        }

        $this->logger->info("Sent ID {$sendRequest->id}");
    }

    private function fail(string $id, string $reason)
    {
        $this->logger->error("Sending ID $id failed: $reason");
        throw new RuntimeException($reason);
    }
}

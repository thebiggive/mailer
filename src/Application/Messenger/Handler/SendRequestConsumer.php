<?php

declare(strict_types=1);

namespace Mailer\Application\Messenger\Handler;

use JetBrains\PhpStorm\Pure;
use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Mime\Email;
use Twig;

/**
 * Takes `SendRequest`s off the SQS/Redis queue and (tries to) send emails.
 */
#[AsMessageHandler]
class SendRequestConsumer
{
    private string $logPepper;

    #[Pure]
    public function __construct(
        private string $appEnv,
        private Config $config,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private Twig\Environment $twig,
        private Validator\SendRequest $validator
    ) {
        $pepper = getenv('LOG_PEPPER');
        $this->logPepper = $pepper === false ? '' : $pepper;
    }

    public function __invoke(SendRequest $sendRequest): void
    {
        $donationId = $sendRequest->params['transactionId'] ?? null;

        // Config can change over time and roll out to the API & consumers at slightly different times, so we should
        // re-validate our `SendRequest`'s params before sending.
        if (!$this->validator->validate($sendRequest, true)) {
            // We don't treat this as permanent (`UnrecoverableExceptionInterface`) in case a change is rolling out and
            // the message could become valid with an imminent consumer update.
            $this->fail($sendRequest->id, "Validation failed: {$this->validator->getReason()}", $donationId);
        }
        $userAgent = $sendRequest->sendingApplication;

        $this->logger->info("Processing ID {$sendRequest->id} for agent $userAgent...");

        $email = new Email();
        $this->embedImages($email, 'BigGive.png', 'tbg-logo');

        $additionalParams['renderHtml'] = true;

        $templateMergeParams = array_merge($additionalParams, $sendRequest->params);

        $templateKey = $sendRequest->templateKey;

        $bodyRenderedHtml = $this->twig->render("{$templateKey}.html.twig", $templateMergeParams);
        $bodyPlainText = strip_tags($this->twig->render("{$templateKey}.html.twig", $sendRequest->params));

        $config = $this->config->get($templateKey);

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(fn($param) => $sendRequest->params[$param], $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        $isNotProduction = $this->appEnv !== 'production';

        if ($isNotProduction) {
            $subject = (bool)$userAgent ?
                "({$this->appEnv} / $userAgent) $subject" :
                "({$this->appEnv}) $subject";
        }

        $fromAddress = getenv('SENDER_ADDRESS');

        $email->addTo($sendRequest->recipientEmailAddress)
            ->from($fromAddress)
            ->subject($subject)
            ->html($bodyRenderedHtml)
            ->text($bodyPlainText)
        ;

        if (
            $isNotProduction && (
            \str_contains($sendRequest->recipientEmailAddress, 'NO_SEND_EMAIL') ||
            \str_contains($sendRequest->recipientEmailAddress, 'tech+regression+credits') ||
            \str_contains($sendRequest->recipientEmailAddress, 'tech+regression+donor')
            )
        ) {
            // saves our usage allowance for testing emails.
            $this->logger->info("Skipping email send for message to $sendRequest->recipientEmailAddress");
            return;
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Mailer transports can bail out with exceptions e.g. on connection timeouts.
            $class = get_class($exception);
            $this->fail($sendRequest->id, "Email send failed. $class: {$exception->getMessage()}", $donationId);
        }

        $pepperedEmailHash = hash('md5', $this->logPepper . '|' . $sendRequest->recipientEmailAddress);

        // to generate a matching hash on Unix command line for use in searching logs,
        // assuming recipient email address is 'fred@example.com' and logPepper is 'myLocalLogPepper', run
        // echo -n "myLocalLogPepper|fred@example.com" | md5sum

        // In production the pepper will be random and unguessable.

        $this->logger->info("Sent ID {$sendRequest->id}, recipientHash: $pepperedEmailHash, template: $templateKey, " .
            "for agent $userAgent");
    }

    /**
     * @param string $id
     * @param string $reason
     * @throws RuntimeException
     */
    private function fail(string $id, string $reason, ?string $donationId): void
    {
        $this->logger->error(
            "Sending ID $id failed: $reason" . ($donationId === null ? '' : " (donation ID $donationId)")
        );
        throw new RuntimeException($reason);
    }

    /**
     * @param Email $email
     * @param string $fileName  Where in top level `images/` to find the original.
     * @param string $cid       Can be used after `cid:` in templates.
     * @link https://symfony.com/doc/current/mailer.html#embedding-images
     */
    private function embedImages(Email $email, string $fileName, string $cid): void
    {
        $pathToImages = dirname(__DIR__, 4) . '/images/';
        $email->embedFromPath($pathToImages . $fileName, $cid);
    }
}

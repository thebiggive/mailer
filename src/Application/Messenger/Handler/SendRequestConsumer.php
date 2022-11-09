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
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Email;
use Twig;

/**
 * Takes `SendRequest`s off the SQS/Redis queue and (tries to) send emails.
 */
class SendRequestConsumer implements MessageHandlerInterface
{
    #[Pure]
    public function __construct(
        private string $appEnv,
        private Config $config,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private Twig\Environment $twig,
        private Validator\SendRequest $validator
    ) {
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

        $email = new Email();
        $this->embedImages($email, 'BigGive.png', 'tbg-logo');

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

        $fromAddress = $sendRequest->forGlobalCampaign
            ? getenv('GLOBAL_SENDER_ADDRESS')
            : getenv('SENDER_ADDRESS');

        $email->addTo($sendRequest->recipientEmailAddress)
            ->from($fromAddress)
            ->subject($subject)
            ->html($bodyRenderedHtml)
            ->text($bodyPlainText)
        ;

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // Mailer transports can bail out with exceptions e.g. on connection timeouts.
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
     * @param Email $email
     * @param string $fileName  Where in top level `images/` to find the original.
     * @param string $cid       Can be used after `cid:` in templates.
     * @return Email
     * @link https://symfony.com/doc/current/mailer.html#embedding-images
     */
    private function embedImages(Email $email, string $fileName, string $cid): Email
    {
        $pathToImages = dirname(__DIR__, 4) . '/images/';

        return $email->embedFromPath($pathToImages . $fileName, $cid);
    }
}

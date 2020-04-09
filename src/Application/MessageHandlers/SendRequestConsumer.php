<?php

declare(strict_types=1);

namespace Mailer\Application\MessageHandlers;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
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

    public function __construct(
        Config $configLoader,
        LoggerInterface $logger,
        Swift_Mailer $mailer,
        Twig\Environment $twig
    ) {
        $this->config = $configLoader;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * @param SendRequest $message
     * @return bool Whether sending succeeded
     */
    public function __invoke(SendRequest $message)/*: bool*/
    {
        // Config can change over time and roll out to the API & consumers at slightly different times, so we should
        // re-validate our `SendRequest`'s params before sending.
//        $this->validator->validate($message); // todo

        try {
            $bodyRenderedHtml = $this->twig->render("{$message->templateKey}.html.twig", $message->params);
        } catch (Twig\Error\LoaderError $ex) {
            $this->logger->error("Template file for {$message->templateKey} not found");
            return false;
        } catch (Twig\Error\Error $ex) {
            $this->logger->error('Template render failed: ' . $ex->getMessage());
            return false;
        }
        $bodyPlainText = strip_tags($bodyRenderedHtml);

        $config = $this->config->get($message->templateKey);
        if ($config === null) {
            $this->logger->error("Template config for {$message->templateKey} not found");
            return false;
        }

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(static function ($subjectParam) use ($message) {
            if (!array_key_exists($subjectParam, $message->params)) {
                throw new \LogicException("Missing subject param '$subjectParam'");
            }

            return $message->params[$subjectParam];
        }, $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        $email = (new \Swift_Message())
            ->addTo($message->recipientEmailAddress)
            ->setSubject($subject)
            ->setBody($bodyPlainText)
            ->addPart($bodyRenderedHtml, 'text/html')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom(getenv('SENDER_ADDRESS'));

        $numberOfRecipients = $this->mailer->send($email);

        if ($numberOfRecipients === 0) {
            // TODO how do we keep failed messages in the queue?
            $this->logger->error('Sending failed');

            throw new RuntimeException('Email send failed');
        }

        $this->logger->info('Sent'); // todo ID?

        return true;
    }
}

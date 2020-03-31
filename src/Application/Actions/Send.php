<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Swift_Mailer;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig;

class Send extends Action
{
    private Swift_Mailer $mailer;
    private Twig\Environment $twig;

    public function __construct(LoggerInterface $logger, Swift_Mailer $mailer, Twig\Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;

        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        // TODO next multipart w/ Twig
        $templateKey = 'donor-donation-success';

        // TODO check for required merge params
        $subject = 'Test mail!';

        try {
            $bodyRenderedHtml = $this->twig->render("{$templateKey}.html.twig", [
                'firstName' => 'testName',
                'subject' => $subject,
            ]);
        } catch (Twig\Error\LoaderError $ex) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'Template not found');
            return $this->respond(new ActionPayload(400, null, $error));
        } catch (Twig\Error\Error $ex) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'Template render failed: ' . $ex->getMessage());
            return $this->respond(new ActionPayload(500, null, $error));
        }

        $bodyPlainText = strip_tags($bodyRenderedHtml);

        $message = (new \Swift_Message())
            ->addTo('noel@noellh.com')
            ->setSubject($subject)
            ->setBody($bodyPlainText)
            ->addPart($bodyRenderedHtml, 'text/html')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom('noel@noellh.com'); // todo use TBG address + configure in env var

        $numberOfRecipients = $this->mailer->send($message);

        // todo move to Sender

        if ($numberOfRecipients > 0) {
            return $this->respondWithData(['status' => 'Sent']);
        }

        $error = new ActionError(ActionError::SERVER_ERROR, 'Send failed');

        return $this->respond(new ActionPayload(500, ['error' => 'Send failed'], $error));
    }
}

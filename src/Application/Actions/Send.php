<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\HttpModels\SendResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Swift_Mailer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig;

/**
 * @OA\Post(
 *     path="/v1/send",
 *     summary="Send an email",
 *     operationId="send",
 *     security={
 *         {"sendHash": {}}
 *     },
 *     @OA\RequestBody(
 *         description="All details needed to send an email",
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/SendRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Email queued to send",
 *         @OA\JsonContent(ref="#/components/schemas/SendResponse"),
 *     ),
 * ),
 */
class Send extends Action
{
    private Config $config;
    private Swift_Mailer $mailer;
    private SerializerInterface $serializer;
    private Twig\Environment $twig;

    public function __construct(
        Config $configLoader,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        Swift_Mailer $mailer,
        Twig\Environment $twig
    ) {
        $this->config = $configLoader;
        $this->mailer = $mailer;
        $this->serializer = $serializer;
        $this->twig = $twig;

        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        /** @var SendRequest $input */
        $input = $this->serializer->deserialize(
            $this->request->getBody(),
            SendRequest::class,
            'json'
        );

        foreach (get_object_vars($input) as $var) {
            if (empty($var)) {
                $error = new ActionError(ActionError::SERVER_ERROR, 'Missing required data');
                return $this->respond(new ActionPayload(400, null, $error));
            }
        }

        $config = $this->config->get($input->templateKey);
        if ($config === null) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'Template config not found');
            return $this->respond(new ActionPayload(400, null, $error));
        }

        foreach ($config->requiredParams as $requiredParam) {
            // For required params, boolean false is fine. undefined and null and blank string are all prohibited.
            if (!isset($input->params[$requiredParam]) || $input->params[$requiredParam] === '') {
                $error = new ActionError(ActionError::SERVER_ERROR, "Missing required param '$requiredParam'");
                return $this->respond(new ActionPayload(400, null, $error));
            }
        }

        // For each $p in the configured subjectParams, we need an array element with $emailData->params[$p].
        $subjectMergeValues = array_map(fn($p) => $input->params[$p], $config->subjectParams);
        $subject = vsprintf($config->subject, $subjectMergeValues);

        try {
            $bodyRenderedHtml = $this->twig->render("{$input->templateKey}.html.twig", $input->params);
        } catch (Twig\Error\LoaderError $ex) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'Template file not found');
            return $this->respond(new ActionPayload(400, null, $error));
        } catch (Twig\Error\Error $ex) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'Template render failed: ' . $ex->getMessage());
            return $this->respond(new ActionPayload(500, null, $error));
        }

        $bodyPlainText = strip_tags($bodyRenderedHtml);

        $message = (new \Swift_Message())
            ->addTo($input->recipientEmailAddress)
            ->setSubject($subject)
            ->setBody($bodyPlainText)
            ->addPart($bodyRenderedHtml, 'text/html')
            ->setContentType('text/html')
            ->setCharset('utf-8')
            ->setFrom(getenv('SENDER_ADDRESS'));

        $numberOfRecipients = $this->mailer->send($message);

        if ($numberOfRecipients > 0) {
            return $this->respondWithData(new SendResponse('queued'));
        }

        $error = new ActionError(ActionError::SERVER_ERROR, 'Send failed');

        return $this->respond(new ActionPayload(500, ['error' => 'Send failed'], $error));
    }
}

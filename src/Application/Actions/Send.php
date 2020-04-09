<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\HttpModels\SendResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

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
    private RoutableMessageBus $bus;
    private Config $config;
    private SerializerInterface $serializer;

    public function __construct(
        Config $configLoader,
        LoggerInterface $logger,
        RoutableMessageBus $bus,
        SerializerInterface $serializer
    ) {
        $this->bus = $bus;
        $this->config = $configLoader;
        $this->serializer = $serializer;

        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        try {
            /** @var SendRequest $input */
            $input = $this->serializer->deserialize(
                $this->request->getBody(),
                SendRequest::class,
                'json'
            );
        } catch (UnexpectedValueException $exception) { // This is the Serializer one, not the global one
            $error = new ActionError(ActionError::BAD_REQUEST, 'Non-deserialisable data');
            return $this->respond(new ActionPayload(400, null, $error));
        }

        foreach (array_keys(get_class_vars(SendRequest::class)) as $property) {
            if (empty($input->{$property})) {
                $error = new ActionError(ActionError::BAD_REQUEST, 'Missing required data');
                return $this->respond(new ActionPayload(400, null, $error));
            }
        }

        $config = $this->config->get($input->templateKey);
        if ($config === null) {
            $error = new ActionError(ActionError::BAD_REQUEST, 'Template config not found');
            return $this->respond(new ActionPayload(400, null, $error));
        }

        foreach ($config->requiredParams as $requiredParam) {
            // For required params, boolean false is fine. undefined and null and blank string are all prohibited.
            if (!isset($input->params[$requiredParam]) || $input->params[$requiredParam] === '') {
                $error = new ActionError(ActionError::BAD_REQUEST, "Missing required param '$requiredParam'");
                return $this->respond(new ActionPayload(400, null, $error));
            }
        }

        $this->bus->dispatch(new Envelope($input, [new BusNameStamp('email')]));

        return $this->respondWithData(new SendResponse('queued'));
    }
}

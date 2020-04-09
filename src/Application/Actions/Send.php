<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\HttpModels\SendResponse;
use Mailer\Application\Validator;
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
    private Validator\SendRequest $validator;

    public function __construct(
        Config $configLoader,
        LoggerInterface $logger,
        RoutableMessageBus $bus,
        SerializerInterface $serializer,
        Validator\SendRequest $validator
    ) {
        $this->bus = $bus;
        $this->config = $configLoader;
        $this->serializer = $serializer;
        $this->validator = $validator;

        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        try {
            /** @var SendRequest $sendRequest */
            $sendRequest = $this->serializer->deserialize(
                $this->request->getBody(),
                SendRequest::class,
                'json'
            );
        } catch (UnexpectedValueException $exception) { // This is the Serializer one, not the global one
            $error = new ActionError(ActionError::BAD_REQUEST, 'Non-deserialisable data');
            return $this->respond(new ActionPayload(400, null, $error));
        }

        if (!$this->validator->validate($sendRequest, false)) {
            $error = new ActionError(ActionError::BAD_REQUEST, $this->validator->getReason());
            return $this->respond(new ActionPayload(400, null, $error));
        }

        $this->bus->dispatch(new Envelope($sendRequest, [new BusNameStamp('email')]));

        return $this->respondWithData(new SendResponse('queued'));
    }
}

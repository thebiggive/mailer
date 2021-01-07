<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use JetBrains\PhpStorm\Pure;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\HttpModels\SendResponse;
use Mailer\Application\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
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
    #[Pure]
    public function __construct(
        LoggerInterface $logger,
        private RoutableMessageBus $bus,
        private SerializerInterface $serializer,
        private Validator\SendRequest $validator
    ) {
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

        $stamps = [
            new BusNameStamp('email'),
            new TransportMessageIdStamp($sendRequest->id),
        ];

        try {
            $this->bus->dispatch(new Envelope($sendRequest, $stamps));
        } catch (TransportException $exception) {
            $this->logger->error(sprintf(
                'SQS send error %s. Request body: %s.',
                $exception->getMessage(),
                $this->request->getBody(),
            ));

            return $this->respond(new ActionPayload(500));
        }

        return $this->respondWithData(new SendResponse('queued', $sendRequest->id));
    }
}

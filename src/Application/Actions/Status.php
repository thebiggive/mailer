<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Redis;
use Slim\Exception\HttpBadRequestException;

/**
 * @OA\Get(
 *     path="/ping",
 *     summary="Check if the service is running and healthy",
 *     operationId="status",
 *     @OA\Response(
 *         response=200,
 *         description="Up and running",
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Having some trouble",
 *     ),
 * ),
 */
class Status extends Action
{
    private ?Redis $redis;

    public function __construct(LoggerInterface $logger, ?Redis $redis)
    {
        $this->redis = $redis;

        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        /** @var string|null $errorMessage */
        $errorMessage = null;

        if ($this->redis === null || !$this->redis->isConnected()) {
            $errorMessage = 'Redis not connected';
        }

        if ($errorMessage === null) {
            return $this->respondWithData(['status' => 'OK']);
        }

        $error = new ActionError(ActionError::SERVER_ERROR, $errorMessage);

        return $this->respond(new ActionPayload(500, ['error' => $errorMessage], $error));
    }
}

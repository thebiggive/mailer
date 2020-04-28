<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
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
    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        return $this->respondWithData(['status' => 'OK']);
    }
}

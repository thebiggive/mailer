<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;

#[OA\Get(
    path: '/ping',
    summary: 'Check if the service is running and healthy',
    operationId: 'status',
    responses: [
        new OA\Response(response: 200, description: 'Up and running'),
        new OA\Response(response: 500, description: 'Having some trouble'),
    ],
)]
final class Status extends Action
{
    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    #[\Override]
    protected function action(): Response
    {
        return $this->respondWithData(['status' => 'OK']);
    }
}

<?php

declare(strict_types=1);

namespace Mailer\Application\Auth;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class SendAuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (false) { // TODO MAIL-2
            return $this->unauthorised($this->logger);
        }

        return $handler->handle($request);
    }

    protected function unauthorised(LoggerInterface $logger): ResponseInterface
    {
        $logger->warning('Unauthorised');

        /** @var ResponseInterface $response */
        $response = new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        $response->getBody()->write(json_encode(['error' => 'Unauthorized'], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

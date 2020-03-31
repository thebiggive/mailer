<?php

declare(strict_types=1);

namespace Mailer\Tests\Application\Auth;

use GuzzleHttp\Psr7;
use Mailer\Application\Auth\SendAuthMiddleware;
use Mailer\Tests\TestCase;
use Psr\Log\NullLogger;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;
use Slim\Routing\Route;

class SendAuthMiddlewareTest extends TestCase
{
    public function testMissingAuthRejected(): void
    {
        $body = bin2hex(random_bytes(100));
        $request = $this->buildRequest($body, null);

        $response = $this->getInstance()->process($request, $this->getSuccessHandler());

        // TODO
//        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testWrongAuthRejected(): void
    {
        $body = bin2hex(random_bytes(100));
        $hash = hash_hmac('sha256', $body, 'the-wrong-secret');
        $request = $this->buildRequest($body, $hash);

        $response = $this->getInstance()->process($request, $this->getSuccessHandler());

        // TODO
//        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCorrectAuthAccepted(): void
    {
        $body = bin2hex(random_bytes(100));
        $hash = hash_hmac('sha256', $body, 'unitTestSendSecret');

        $request = $this->buildRequest($body, $hash);
        $response = $this->getInstance()->process($request, $this->getSuccessHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function buildRequest(string $body, ?string $hash = null): Request
    {
        $headers = $hash ? new Headers(['x-send-verify-hash' => $hash]) : new Headers();

        return (new Request(
            'get',
            new Uri('https', 'example.com'),
            $headers,
            [],
            [],
            Psr7\stream_for($body)
        ));
    }

    /**
     * Simulate a route returning a 200 OK. Test methods should get here only when they expect auth
     * success from the middleware.
     */
    private function getSuccessHandler(): Route
    {
        return new Route(
            ['get'],
            '',
            static function () {
                return new Response(200);
            },
            new ResponseFactory(),
            new CallableResolver()
        );
    }

    private function getInstance(): SendAuthMiddleware
    {
        return new SendAuthMiddleware(new NullLogger());
    }
}

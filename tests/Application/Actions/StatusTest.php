<?php

declare(strict_types=1);

namespace Mailer\Tests\Application\Actions;

use Mailer\Application\Actions\ActionPayload;
use Mailer\Tests\TestCase;

class StatusTest extends TestCase
{
    public function testRedisErrorWithDummyHostname(): void
    {
        $app = $this->getAppInstance();

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(500, ['error' => 'Redis not connected']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }
}

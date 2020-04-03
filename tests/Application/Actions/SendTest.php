<?php

declare(strict_types=1);

namespace Mailer\Tests\Application\Actions\Donations;

use DI\Container;
use Mailer\Application\Actions\ActionPayload;
use Mailer\Tests\TestCase;
use Prophecy\Argument;
use Swift_Mailer;
use Swift_Message;

class SendTest extends TestCase
{
    public function testMissingAuth(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        // No auth header
        $request = $this->createRequest('POST', '/v1/send', $data);
        $response = $app->handle($request);

        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(401, ['error' => 'Unauthorized']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBadAuth(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        // Bad auth header
        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader('wrongData'));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(401, ['error' => 'Unauthorized']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeserialiseError(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = '{"not-good-json';

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(400, ['error' => [
            'type' => 'BAD_REQUEST',
            'description' => 'Non-deserialisable data',
        ]]);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testMissingModelProperty(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = json_encode([
            'templateKey' => 'some-key',
            'recipientEmailAddress' => 'test@example.com',
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedPayload = new ActionPayload(400, ['error' => [
            'type' => 'BAD_REQUEST',
            'description' => 'Missing required data',
        ]]);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testMissingRequiredMergeParam(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => ['someOtherParam' => 'some value'],
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedPayload = new ActionPayload(400, ['error' => [
            'type' => 'BAD_REQUEST',
            'description' => "Missing required param 'campaignName'",
        ]]);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUnknownTemplateKey(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(false));

        $data = json_encode([
            'templateKey' => 'some-key',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedPayload = new ActionPayload(400, ['error' => [
            'type' => 'BAD_REQUEST',
            'description' => 'Template config not found',
        ]]);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * All valid input but the send() call fails / returns 0 recipients.
     */
    public function testSendError(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(true, false));

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedPayload = new ActionPayload(500, ['error' => 'Send failed']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testSuccess(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $container->set(Swift_Mailer::class, $this->getTestSwiftMailer(true));

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedPayload = new ActionPayload(200, ['status' => 'queued']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @param bool $sendExpected
     * @param bool $successfulSend  If we are simulating a send, should it succeed?
     * @return Swift_Mailer
     */
    private function getTestSwiftMailer(bool $sendExpected, bool $successfulSend = true): Swift_Mailer
    {
        $swiftMailerProphecy = $this->prophesize(Swift_Mailer::class);
        if ($sendExpected) {
            $swiftMailerProphecy->send(Argument::type(Swift_Message::class))
                ->willReturn($successfulSend ? 1 : 0) // Number of successful recipients
                ->shouldBeCalledOnce();
        } else {
            $swiftMailerProphecy->send(Argument::type(Swift_Message::class))->shouldNotBeCalled();
        }

        return $swiftMailerProphecy->reveal();
    }

    private function getFullDonorParams(): array
    {
        return [
            'campaignName' => 'The campaign',
            'campaignThankYouMessage' => "TYVM\n\nLove, the charity xx",
            'charityName' => 'The charity',
            'donationAmount' => 400.01,
            'donorFirstName' => 'Patti',
            'donorLastName' => 'Smith',
            'isGiftAidClaimed' => false,
            'isMatched' => true,
            'matchedAmount' => 200.01,
            'tipAmount' => 10,
            'totalChargedAmount' => 410.01,
            'totalCharityValueAmount' => 600.02,
            'transactionId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ];
    }

    private function getAuthHeader(string $data): array
    {
        return ['x-send-verify-hash' => hash_hmac('sha256', $data, getenv('SEND_SECRET'))];
    }
}

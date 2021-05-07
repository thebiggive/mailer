<?php

declare(strict_types=1);

namespace Mailer\Tests\Application\Actions;

use DI\Container;
use Mailer\Application\Actions\ActionPayload;
use Mailer\Tests\TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SendTest extends TestCase
{
    public function testMissingAuth(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
        $this->assertCount(0, $transport->getSent());
    }

    public function testBadAuth(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
        $this->assertCount(0, $transport->getSent());
    }

    public function testDeserialiseError(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
        $this->assertCount(0, $transport->getSent());
    }

    public function testMissingModelProperty(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
        $this->assertCount(0, $transport->getSent());
    }

    public function testMissingRequiredMergeParam(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
        $this->assertCount(0, $transport->getSent());
    }

    public function testUnknownTemplateKey(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

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
            'description' => 'Template config for some-key not found',
        ]]);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($expectedSerialised, $payload);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertCount(0, $transport->getSent());
    }

    /**
     * All valid input but the send() call fails / returns 0 recipients.
     */
    public function testQueueError(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::type('string'))->shouldBeCalledOnce();
        $container->set(LoggerInterface::class, $logger->reveal());

        $failingInMemoryTransportProphecy = $this->prophesize(InMemoryTransport::class);
        $failingInMemoryTransportProphecy->send(Argument::type(Envelope::class))->willThrow(
            new TransportException('Some transport error')
        );
        $container->set(TransportInterface::class, $failingInMemoryTransportProphecy->reveal());

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('[]', (string) $response->getBody());
    }

    public function testSuccess(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $transport = new InMemoryTransport();
        $container->set(TransportInterface::class, $transport);

        $data = json_encode([
            'templateKey' => 'donor-donation-success',
            'recipientEmailAddress' => 'test@example.com',
            'params' => $this->getFullDonorParams(),
        ], JSON_THROW_ON_ERROR, 512);

        $request = $this->createRequest('POST', '/v1/send', $data, $this->getAuthHeader($data));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $payloadData = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('queued', $payloadData['status']);
        $this->assertMatchesRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/',
            $payloadData['id']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $transport->getSent());
    }

    private function getFullDonorParams(): array
    {
        return [
            'campaignName' => 'The campaign',
            'campaignThankYouMessage' => "TYVM\n\nLove, the charity xx",
            'charityName' => 'The charity',
            'currencyCode' => 'GBP',
            'donationAmount' => 400.01,
            'donorFirstName' => 'Patti',
            'donorLastName' => 'Smith',
            'giftAidAmountClaimed' => 100.00,
            'matchedAmount' => 200.01,
            'statementReference' => 'The Big Give The char',
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

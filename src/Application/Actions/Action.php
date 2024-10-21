<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

#[OA\Info(title: "Big Give Mailer", version: "1")]
#[OA\Server(description: "Staging", url: "https://mailer-staging.thebiggivetest.org.uk")]
#[OA\SecurityScheme(
    type: "apiKey",
    in: "header",
    securityScheme: "sendHash",
    name: "x-send-verify-hash",
    description: "A variable content hash based on a shared webhook secret. To calculate
                    the expected hash, trim leading and trailing whitespace from the
                    JSON body, and get an HMAC SHA-256 digest using your `SEND_SECRET` as
                    the key. Convert the hash digest to lowercase hexits. So, in pseudocode,
                    verify_hash = lowercase_hex(
                        hash_hmac(
                            'sha256',
                            trim(json_body_text),
                            SEND_SECRET
                        )
                    )"
)]
abstract class Action
{
    protected Request $request;
    protected Response $response;
    protected array $args;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     * @return Response
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        return $this->action();
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    abstract protected function action(): Response;

    /**
     * @param  string $name
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }

    /**
     * @param  array|object|null $data
     * @return Response
     */
    protected function respondWithData($data = null): Response
    {
        $payload = new ActionPayload(200, $data);
        return $this->respond($payload);
    }

    /**
     * @param ActionPayload $payload
     * @return Response
     * @throws \JsonException
     */
    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
            ->withStatus($payload->getStatusCode())
            ->withHeader('Content-Type', 'application/json');
    }
}

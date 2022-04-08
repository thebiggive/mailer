<?php

declare(strict_types=1);

namespace Mailer\Application\Actions;

use JetBrains\PhpStorm\Pure;
use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    public function __construct(
        private int $statusCode = 200,
        private array | object | null $data = null,
        private ?ActionError $error = null
    ) {
    }

    /**
     * @return int
     */
    #[Pure]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null|object
     */
    #[Pure]
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return ActionError|null
     */
    #[Pure]
    public function getError(): ?ActionError
    {
        return $this->error;
    }

    public function jsonSerialize(): array|object|null
    {
        $payload = [];
        if ($this->data !== null) {
            $payload = $this->data;
        } elseif ($this->error !== null) {
            $payload = ['error' => $this->error];
        }

        return $payload;
    }
}

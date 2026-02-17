<?php

declare(strict_types=1);

namespace Mailer\Application\HttpModels;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Send Response",
    type: "object",
)]
final class SendResponse
{
    #[OA\Property(description: '"queued" on success', example: "queued")]
    public string $status;

    #[OA\Property(
        description: 'UUID for the queued message delivery',
        format: 'uuid',
        example: "f7095caf-7180-4ddf-a212-44bacde69066"
    )]
    public string $id;

    public function __construct(string $status, string $id)
    {
        $this->status = $status;
        $this->id = $id;
    }
}

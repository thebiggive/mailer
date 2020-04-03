<?php

declare(strict_types=1);

namespace Mailer\Application\HttpModels;

/**
 * @OA\Schema(
 *     type="object",
 *     title="Send Response",
 * )
 */
class SendResponse
{
    /**
     * @var string 'queued' on success
     * @OA\Property(property="status", format="string", example="queued")
     */
    public string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }
}

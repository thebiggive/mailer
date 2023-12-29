<?php

declare(strict_types=1);

namespace Mailer\Application\HttpModels;

use OpenApi\Annotations as OA;

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

    /**
     * @var string UUID for the queued message delivery
     * @OA\Property(
     *     property="id",
     *     format="uuid",
     *     example="f7095caf-7180-4ddf-a212-44bacde69066",
     *     pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
     * )
     */
    public string $id;

    public function __construct(string $status, string $id)
    {
        $this->status = $status;
        $this->id = $id;
    }
}

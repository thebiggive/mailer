<?php

declare(strict_types=1);

namespace Mailer\Application\HttpModels;

use Ramsey\Uuid\Uuid;

/**
 * @todo once swagger-php supports class property types, remove explicit `property=` annotations.
 * @link https://github.com/zircote/swagger-php/issues/742
 *
 * @OA\Schema(
 *     type="object",
 *     title="Send Request",
 *     required={"templateKey", "recipientEmailAddress", "params"},
 * )
 */
class SendRequest
{
    /**
     * @var string
     * @OA\Property(property="templateKey", format="string", example="donor-donation-success")
     */
    public string $templateKey;

    /**
     * @var string
     * @OA\Property(property="recipientEmailAddress", format="string", example="recipient@example.com")
     */
    public string $recipientEmailAddress;

    /**
     * @var string[]
     * @OA\Property(
     *     property="params",
     *     format="object",
     *     description="Object mapping parameter placeholder keys to their merge values",
     *     @OA\Items(type="string"),
     *     example={
     *       "campaignName": "The campaign",
     *       "campaignThankYouMessage": "TYVM\n\nLove, the charity xx",
     *       "charityName": "The charity",
     *       "donationAmount": 400.01,
     *       "donorFirstName": "Patti",
     *       "donorLastName": "Smith",
     *       "isGiftAidClaimed": false,
     *       "isMatched": true,
     *       "matchedAmount": 200.01,
     *       "tipAmount": 10,
     *       "totalChargedAmount": 410.01,
     *       "totalCharityValueAmount": 600.02,
     *       "transactionId": "d290f1ee-6c54-4b01-90e6-d701748f0851",
     *     }
     * ),
     */
    public array $params;

    public string $id;

    public function __construct()
    {
        $this->id = (Uuid::uuid4())->toString();
    }
}

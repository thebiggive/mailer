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

    public ?bool $forGlobalCampaign = false;

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
     *       "charityEmailAddress": "charity@example.com",
     *       "charityLogoUri": "https://donate.biggive.org/assets/images/logo.png",
     *       "charityName": "The charity",
     *       "charityNumber": "1234765",
     *       "charityPhoneNumber": "+1 111 111122",
     *       "paymentMethodType": "card",
     *       "charityPostalAddress": "123 Main St, London, N1 1AA, United Kingdom",
     *       "charityRegistrationAuthority": "Charity Commission for England and Wales",
     *       "charityWebsite": "https://example.com",
     *       "currencyCode": "GBP",
     *       "donationAmount": 400.01,
     *       "donationDatetime": "2021-05-01T00:00:00Z",
     *       "donorFirstName": "Patti",
     *       "donorLastName": "Smith",
     *       "feeCoverAmount": 5,
     *       "giftAidAmountClaimed": 100.00,
     *       "matchedAmount": 200.01,
     *       "paymentMethodType": "card",
     *       "statementReference": "The Big Give The char",
     *       "tipAmount": 10,
     *       "totalChargedAmount": 415.01,
     *       "totalCharityValueAmount": 600.02,
     *       "transactionId": "d290f1ee-6c54-4b01-90e6-d701748f0851",
     *     }
     * ),
     */
    public array $params;

    public string $id;
    public string $env;

    public function __construct()
    {
        $this->id = (Uuid::uuid4())->toString();
        $this->env = getenv('APP_ENV');
    }
}

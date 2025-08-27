<?php

declare(strict_types=1);

namespace Mailer\Application\HttpModels;

use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;

#[OA\Schema(type: 'object', title: 'Send Request', required: ['templateKey', 'recipientEmailAddress', 'params'])]
class SendRequest implements MessageGroupAwareInterface
{
    #[OA\Property(example: "donor-donation-success")]
    public string $templateKey;

    #[OA\Property(example: "recipient@example.com")]
    public string $recipientEmailAddress;

    /**
     * @var string[]
     */
    #[OA\Property(
        format: 'object',
        description: 'Object mapping parameter placeholder keys to their merge values',
        items: new OA\Items(type: 'string'),
        example: [
            "campaignName" => "The campaign",
            "campaignThankYouMessage" => "TYVM\n\nLove, the charity xx",
            "charityEmailAddress" => "charity@example.com",
            "charityLogoUri" => "https://donate.biggive.org/assets/images/logo.png",
            "charityName" => "The charity",
            "charityNumber" => "1234765",
            "charityPhoneNumber" => "+1 111 111122",
            "charityRegistrationAuthority" => "Charity Commission for England and Wales",
            "charityWebsite" => "https://example.com",
            "createAccountUri" => "https://donate.biggive.org/register?c=000000&u=11112222-6c54-4b01-90e6-d701748f0852",
            "currencyCode" => "GBP",
            "donationAmount" => 400.01,
            "donationDatetime" => "2021-05-01T00:00:00Z",
            "donorFirstName" => "Patti",
            "donorLastName" => "Smith",
            "giftAidAmountClaimed" => 100.00,
            "matchedAmount" => 200.01,
            "paymentMethodType" => "card",
            "statementReference" => "The Big Give The char",
            "tipAmount" => 10,
            "totalChargedAmount" => 415.01,
            "totalCharityValueAmount" => 600.02,
            "transactionId" => "d290f1ee-6c54-4b01-90e6-d701748f0851",
        ]
    )]
    public array $params;

    public string $id;
    public string $env;

    /**
     * Identifies the application that asked Mailer to send this message. Not necassarily sent by all applications,
     * so may be null.
     */
    public ?string $sendingApplication = null;

    public function __construct()
    {
        $this->id = (Uuid::uuid4())->toString();
        $this->env = getenv('APP_ENV');
        $this->sendingApplication = null;
    }

    #[\Override]
    public function getMessageGroupId(): string
    {
        return "send-request-{$this->id}";
    }
}

<?php

namespace Mailer\Tests\templates;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

final class DonorDonationSuccessTest extends TestCase
{
    use MatchesSnapshots;

    public function testSendRendersForDonationFunds(): void
    {
        $rendered = Renderer::renderMessage(
            'donor-donation-success.html.twig',
            [
                'currencyCode' => 'GBP',
                'charityName' => 'CHARITY',
                'campaignThankYouMessage' => 'Thank you from our campaign!',
                'campaignName' => 'CAMPAIGN',
                'charityNumber' => '123CHARITY_NUMBER_4567',
                'charityRegistrationAuthority' => 'Charity reg authority',
                'donationDatetime' => new \DateTimeImmutable('2023-01-30'),
                'donorFirstName' => 'Joe',
                'donorLastName' => 'Bloggs',
                'totalCharityValueAmount' => 50_000,
                'totalChargedAmount' => 25_000,
                'donationAmount' => 25_000,
                'giftAidAmountClaimed' => 1_000,
                'matchedAmount' => 25_000,
                'transactionId' => 'xyz_transaction_id_zyz',
                'paymentMethodType' => 'customer_balance',
                'statementReference' => '235235_ref_statement_ref12531',
            ]
        );

        $this->assertMatchesSnapshot($rendered);
    }
}

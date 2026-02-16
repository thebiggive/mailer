<?php

namespace Mailer\Tests\templates;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

final class RegularGivingConfirmationTest extends TestCase
{
    use MatchesSnapshots;

    public function testItRenders(): void
    {
        $rendered = Renderer::renderMessage(
            'donor-mandate-confirmation.html.twig',
            [
                'charityName' => '[Charity Name]',
                'campaignName' => '[Campaign Name]',
                'charityNumber' => '[Charity Number]',
                'charityRegistrationAuthority' => '[Some Commission]',
                'campaignThankYouMessage' => '[Campaign Thank You Message]',

                'signupDate' => 'DD/MM/YYYY HH:MM',
                'donorName' => '[Donor Name]',
                'schedule' => 'Monthly on day #3',
                'nextPaymentDate' => 'MM/DD/YYYY',
                'amount' => '£20.00',
                'giftAidValue' => '£4.00',
                'totalIncGiftAid' => '£24.00',
                'totalCharged' => '£20.00',

                'firstDonation' => [
                    // mostly same keys as used on the donorDonationSuccess email
                    'donationDatetime' => new \DateTimeImmutable('2023-01-30'),
                    'currencyCode' => 'GBP',
                    'charityName' => '[Charity Name]',
                    'donationAmount' => 25_000,
                    'giftAidAmountClaimed' => 1_000,
                    'totalWithGiftAid' => 26_000,
                    'matchedAmount' => 25_000,
                    'totalCharityValueAmount' => 50_000,
                    'transactionId' => '[PSP Transaction ID]',
                    'statementReference' => 'The Big Give [Charity Name]'
                ],
            ]
        );

        $this->assertMatchesSnapshot($rendered);
    }
}

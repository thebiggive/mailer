<?php

declare(strict_types=1);

namespace Mailer\Tests\templates;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;

final class DonorFundsThanksTest extends TestCase
{
    use MatchesSnapshots;

    public function testItRendersEmailToThankDonorForFundingAnAccount(): void
    {
        $rendered = Renderer::renderMessage(
            'donor-funds-thanks.html.twig',
            [
                'donorFirstName' => 'Josh',
                // taking the money amounts as strings not numbers - although other emails such as
                // donor-donation-success take money amounts as numbers and format them, I feel like its better to give
                // the responsibility for formatting money amount as a string to the calling service e.g. matchbot.
                'transferAmount' => "£500",
            ]
        );

        $this->assertMatchesSnapshot($rendered);
    }
}

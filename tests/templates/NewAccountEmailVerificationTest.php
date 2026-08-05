<?php

declare(strict_types=1);

namespace Mailer\Tests\templates;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

final class NewAccountEmailVerificationTest extends TestCase
{
    use MatchesSnapshots;

    public function testItRendersEmailToThankDonorForFundingAnAccount(): void
    {
        $rendered = Renderer::renderMessage(
            'new-account-email-verification.html.twig',
            [
                'secretCode' => '123321',
            ]
        );

        $this->assertMatchesSnapshot($rendered);
    }
}

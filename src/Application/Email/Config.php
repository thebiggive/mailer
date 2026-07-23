<?php

declare(strict_types=1);

namespace Mailer\Application\Email;

use Mailer\Application\ConfigModels\Email;

final class Config
{
    /** @param $emailSettings[] Keyed on template key */
    public function __construct(private array $emailSettings)
    {
        $this->emailSettings = array_map(fn ($config) => Email::fromConfigArray($config), $emailSettings);
    }

    public function get(string $templateKey): ?Email
    {
        if (getenv('APP_ENV') !== 'production' && $templateKey === 'issue-warning-please') {
            \trigger_error('Testing how we handle warnings', \E_USER_WARNING);
        }

        $configs = array_filter($this->emailSettings, fn($theSetting) => $theSetting->templateKey === $templateKey);
        if (count($configs) === 0) {
            return null;
        }

        return current($configs);
    }
}

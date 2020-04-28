<?php

declare(strict_types=1);

namespace Mailer\Application\Email;

use Mailer\Application\ConfigModels\Email;

class Config
{
    /** @var Email[] Keyed on template key */
    private array $emailSettings;

    public function __construct(array $emailSettings)
    {
        $this->emailSettings = array_map(fn ($config) => Email::fromConfigArray($config), $emailSettings);
    }

    public function get(string $templateKey): ?Email
    {
        $configs = array_filter($this->emailSettings, fn($theSetting) => $theSetting->templateKey === $templateKey);
        if (count($configs) === 0) {
            return null;
        }

        return current($configs);
    }
}

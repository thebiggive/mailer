<?php

namespace Mailer\Tests\templates;

use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;

final class Renderer
{
    public static function renderMessage(string $templateFileName, array $data): string
    {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../templates'));
        $twig->addExtension(new CssInlinerExtension());
        $twig->addExtension(new IntlExtension(
            new \IntlDateFormatter('en-GB'),
            new \NumberFormatter('en-GB', \NumberFormatter::CURRENCY)
        ));

        return $twig->render($templateFileName, $data);
    }
}

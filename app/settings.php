<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'appEnv' => getenv('APP_ENV'),

            'displayErrorDetails' => (getenv('APP_ENV') === 'local'),

            // Templates live in `templates/{template key}.html.twig`. For each email, set the following config keys:
            // * subject: A string with %s placeholders for any merge fields
            // * subjectParams: An array corresponding to those %s values, in order. Repeats allowed.
            //                  Empty array for no placeholders.
            // * requiredParams:    The parameters we need populated to render the template successfully. If any are
            //                      blank (boolean false is OK) or missing, emails will refuse to send. Note that you
            //                      *can* currently merge in params without listing them here, but it's probably safer
            //                      and clearer to avoid this.
            'emails' => [
                'donor-donation-success' => [
                    'subject' => 'Thanks for your donation, %s!',
                    'subjectParams' => ['firstName'],
                    'requiredParams' => ['amount', 'firstName', 'giftAidStatus'], // TODO finish this
                ],
            ],

            'logger' => [
                'name' => 'mailer',
                'path' => 'php://stdout',
                'level' => Logger::DEBUG,
            ],

            'redis' => [
                'host' => getenv('REDIS_HOST'),
            ],

            'swift' => [
                // Processed in line with Symfony's conventions for `url` property / `MAILER_URL` env var.
                // https://symfony.com/doc/current/reference/configuration/swiftmailer.html#url
                'mailerUrl' => getenv('MAILER_URL'),
            ],

            'twig' => [
                'templatePath' => dirname(__DIR__) . '/templates',
                'cachePath' => dirname(__DIR__) . '/var/twig',
                'debug' => (getenv('APP_ENV') === 'local'), // Disables caching & enables debug output
            ]
        ],
    ]);
};

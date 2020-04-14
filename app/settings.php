<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Mailer\Application\ConfigModels\Email;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'appEnv' => getenv('APP_ENV'),

            'displayErrorDetails' => (getenv('APP_ENV') === 'local'),

            /**
             * 'emails' is an array of Email configs. @see Email for properties. Note that you cannot
             * directly instantiate objects here without breaking PHP-DI compilation, so we use an array
             * and do it at runtime.
             */
            'emails' => [
                [
                    'templateKey' => 'donor-donation-success',
                    'subject' => 'Thanks for your donation, %s!',
                    'subjectParams' => ['donorFirstName'],
                    'requiredParams' => [
                        'campaignName',
                        'campaignThankYouMessage',
                        'charityName',
                        'donationAmount',
                        'donorFirstName',
                        'donorLastName',
                        'isGiftAidClaimed',
                        'isMatched',
                        'matchedAmount',
                        'tipAmount',
                        'totalChargedAmount',
                        'totalCharityValueAmount',
                        'transactionId',
                    ],
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

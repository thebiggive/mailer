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
                        'currencyCode',
                        'donationAmount',
                        'donorFirstName',
                        'paymentMethodType',
                        'donorLastName',
                        'feeCoverAmount',
                        'giftAidAmountClaimed',
                        'matchedAmount',
                        'tipAmount',
                        'totalChargedAmount',
                        'totalCharityValueAmount',
                        'transactionId',
                    ],
                ],
                [
                    'templateKey' => 'pledger-success',
                    'subject' => 'Thank you for your pledge',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'campaignEndDate',
                        'campaignName',
                        'campaignPledgeSubmissionDeadline',
                        'campaignStartDate',
                        'charityName',
                        'currencyCode',
                        'pledgeAmount',
                        'pledgerFirstName',
                        'pledgerLastName',
                    ],
                ],
                [
                    'templateKey' => 'donor-donation-refund-full',
                    'subject' => 'Full Donation Refund',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'donorFirstName',
                        'transactionId',
                        'charityName',
                        'donationAmount',
                        'donationTipAmount',
                    ],
                ],
                [
                    'templateKey' => 'donor-donation-refund-tip',
                    'subject' => 'Tip Refund',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'donorFirstName',
                        'transactionId',
                        'charityName',
                        'donationTipAmount',
                    ],
                ],
                [
                    'templateKey' => 'donor-registered',
                    'subject' => 'You are registered with Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'donorFirstName',
                        'donorEmail'
                    ],
                ],
                [
                    'templateKey' => 'password-reset-requested',
                    'subject' => 'Reset your password for Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'firstName',
                        'lastName',
                        'resetLink',
                    ],
                ],
            ],

            'logger' => [
                'name' => 'mailer',
                'path' => 'php://stdout',
                'level' => Logger::DEBUG,
            ],

            'mailer' => [
                // Used for various transport configuration by Symfony Mailer.
                // See https://symfony.com/doc/current/mailer.html#using-built-in-transports
                'dsn' => getenv('MAILER_DSN'),
            ],

            'twig' => [
                'templatePath' => dirname(__DIR__) . '/templates',
                'cachePath' => dirname(__DIR__) . '/var/twig',
                'debug' => (getenv('APP_ENV') === 'local'), // Disables caching & enables debug output
            ],
        ],
    ]);
};

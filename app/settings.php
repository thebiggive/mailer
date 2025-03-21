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
                        'charityName',
                        'currencyCode',
                        'donationAmount',
                        'donorFirstName',
                        'paymentMethodType',
                        'donorLastName',
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
                    'templateKey' => 'donor-funds-thanks',
                    'subject' => 'Confirmation of Donation Funds Received',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'donorFirstName',
                        'transferAmount',
                    ],
                ],
                [
                    'templateKey' => 'donor-mandate-confirmation',
                    'subject' => 'Thanks for setting up a regular gift to %s',
                    'subjectParams' => ['charityName'],
                    'requiredParams' => [
                        'charityName',
                        'campaignName',
                        'campaignThankYouMessage',
                        'signupDate',
                        'donorName',
                        'schedule',
                        'nextPaymentDate',
                        'amount',
                        'giftAidValue',
                        'totalIncGiftAid',
                        'totalCharged',
                        'firstDonation',
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

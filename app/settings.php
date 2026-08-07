<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Mailer\Application\ConfigModels\Email;
use Mailer\Application\Email\Config as C;
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
                    'templateKey' => C::key('donor-donation-success.html.twig'),
                    'subject' => 'Thanks for your donation, %s!',
                    'subjectParams' => ['donorGreetingName'],
                    'requiredParams' => [
                        'campaignName',
                        'charityName',
                        'currencyCode',
                        'donationAmount',
                        'paymentMethodType',
                        'donorGreetingName',
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
                    'templateKey' => C::key('pledger-success.html.twig'),
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
                    'templateKey' => C::key('donor-donation-refund-full.html.twig'),
                    'subject' => 'Full Donation Refund',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'transactionId',
                        'charityName',
                        'donationAmount',
                        'donationTipAmount',
                    ],
                ],
                [
                    'templateKey' => C::key('donor-regular-donation-failed-payment.html.twig'),
                    'subject' => "We couldn't collect your regular donation for %s",
                    'subjectParams' => ['charityName'],
                    'requiredParams' => [
                        'charityName',
                    ],
                ],
                [
                    'templateKey' => C::key('donor-donation-refund-tip.html.twig'),
                    'subject' => 'Tip Refund',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'transactionId',
                        'charityName',
                        'donationTipAmount',
                    ],
                ],
                [
                    'templateKey' => C::key('donor-funds-thanks.html.twig'),
                    'subject' => 'Confirmation of Donation Funds Received',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'transferAmount',
                    ],
                ],
                [
                    'templateKey' => C::key('donor-mandate-confirmation.html.twig'),
                    'subject' => 'Thanks for setting up a regular gift to %s',
                    'subjectParams' => ['charityName'],
                    'requiredParams' => [
                        'charityName',
                        'campaignName',
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
                    'templateKey' => C::key('donor-registered.html.twig'),
                    'subject' => 'You are registered with Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'donorEmail'
                    ],
                ],
                [
                    'templateKey' => C::key('password-reset-requested.html.twig'),
                    'subject' => 'Reset your password for Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
                        'lastName',
                        'resetLink',
                    ],
                ],
                [
                    'templateKey' => C::key('new-account-email-verification.html.twig'),
                    'subject' => '%s is your Big Give verification code',
                    'subjectParams' => ['secretCode'],
                    'requiredParams' => [
                        'secretCode',
                    ],
                ],
                [
                    'templateKey' => C::key('new-account-email-verification-regular-giving.html.twig'),
                    'subject' => 'Your temporary password for regular giving',
                    'subjectParams' => ['secretCode'],
                    'requiredParams' => [
                        'secretCode',
                    ],
                ],
                [
                    'templateKey' => C::key('new-account-email-already-registered.html.twig'),
                    'subject' => 'You are already registered with Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
                    ],
                ],
                [
                    'templateKey' => C::key('new-account-email-already-registered-regular-giving.html.twig'),
                    'subject' => 'You are already registered with Big Give',
                    'subjectParams' => [],
                    'requiredParams' => [
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

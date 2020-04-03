<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Mailer\Application\Auth;
use Mailer\Application\Email\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Openbuildings\Swiftmailer\CssInlinerPlugin;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        Auth\SendAuthMiddleware::class => static function (ContainerInterface $c): Auth\SendAuthMiddleware {
            return new Auth\SendAuthMiddleware($c->get(LoggerInterface::class));
        },

        Config::class => static function (ContainerInterface $c): Config {
            return new Config($c->get('settings')['emails']);
        },

        LoggerInterface::class => static function (ContainerInterface $c) {
            $settings = $c->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        Redis::class => static function (ContainerInterface $c): ?Redis {
            $redis = new Redis();
            try {
                $redis->connect($c->get('settings')['redis']['host']);
            } catch (RedisException $exception) {
                return null;
            }

            return $redis;
        },

        SerializerInterface::class => static function (ContainerInterface $c): SerializerInterface {
            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            return new Serializer($normalizers, $encoders);
        },

        Swift_Mailer::class => static function (ContainerInterface $c): Swift_Mailer {
            $mailerUrlPieces = parse_url($c->get('settings')['swift']['mailerUrl']);
            if ($mailerUrlPieces['scheme'] !== 'smtp') {
                throw new \LogicException("Unsupported mailer URL scheme {$mailerUrlPieces['scheme']}");
            }

            $transport = new Swift_SmtpTransport(
                $mailerUrlPieces['host'],
                $mailerUrlPieces['port'],
                $mailerUrlPieces['query']['encryption'] ?? null,
            );
            if (!empty($mailerUrlPieces['user'])) {
                $transport->setUsername($mailerUrlPieces['user']);
            }
            if (!empty($mailerUrlPieces['pass'])) {
                $transport->setPassword($mailerUrlPieces['pass']);
            }

            $mailer = new Swift_Mailer($transport);
            $mailer->registerPlugin(new CssInlinerPlugin());

            return $mailer;
        },

        Twig\Environment::class => static function (ContainerInterface $c): Twig\Environment {
            $twigSettings = $c->get('settings')['twig'];
            $loader = new Twig\Loader\FilesystemLoader($twigSettings['templatePath']);
            return new Twig\Environment($loader, [
                'cache' => $twigSettings['cachePath'],
                'debug' => $twigSettings['debug'],
            ]);
        },
    ]);
};

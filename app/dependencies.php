<?php

declare(strict_types=1);

use DI\Container;
use DI\ContainerBuilder;
use Mailer\Application\Auth;
use Mailer\Application\Email\Config;
use Mailer\Application\HttpModels\SendRequest;
use Mailer\Application\Messenger\Handler\SendRequestConsumer;
use Mailer\Application\Validator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Extra\Intl\IntlExtension;

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

        MessageBusInterface::class => static function (ContainerInterface $c): MessageBusInterface {
            return new MessageBus([
                new SendMessageMiddleware(new SendersLocator(
                    [SendRequest::class => [TransportInterface::class]],
                    $c,
                )),
                new HandleMessageMiddleware(new HandlersLocator(
                    [SendRequest::class => [$c->get(SendRequestConsumer::class)]],
                )),
            ]);
        },

        RoutableMessageBus::class => static function (ContainerInterface $c): RoutableMessageBus {
            $busContainer = new Container();
            $busContainer->set('email', $c->get(MessageBusInterface::class)); // async, currently via SQS or Redis

            return new RoutableMessageBus($busContainer);
        },

        SendRequestConsumer::class => static function (ContainerInterface $c): SendRequestConsumer {
            return new SendRequestConsumer(
                $c->get('settings')['appEnv'],
                $c->get(Config::class),
                $c->get(LoggerInterface::class),
                $c->get(MailerInterface::class),
                $c->get(Twig\Environment::class),
                $c->get(Validator\SendRequest::class),
            );
        },

        SerializerInterface::class => static function (ContainerInterface $c): SerializerInterface {
            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            return new Serializer($normalizers, $encoders);
        },

        MailerInterface::class => static function (ContainerInterface $c): MailerInterface {
            // Timeout comes from php.ini's `default_socket_timeout` – 8 seconds in our Docker base.
            // See https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport
            $transport = (new EsmtpTransportFactory())->create(
                $c->get('settings')['mailer']['dsn'],
            );
            return new Symfony\Component\Mailer\Mailer($transport);
        },

        TransportInterface::class => static function (ContainerInterface $c): TransportInterface {
            $transportFactory = new TransportFactory([
                new AmazonSqsTransportFactory(),
                new RedisTransportFactory(),
            ]);
            return $transportFactory->createTransport(
                getenv('MESSENGER_TRANSPORT_DSN'),
                [],
                new PhpSerializer(),
            );
        },

        Twig\Environment::class => static function (ContainerInterface $c): Twig\Environment {
            $twigSettings = $c->get('settings')['twig'];
            $loader = new Twig\Loader\FilesystemLoader($twigSettings['templatePath']);
            $env = new Twig\Environment($loader, [
                'cache' => $twigSettings['cachePath'],
                'debug' => $twigSettings['debug'],
            ]);
            $env->addExtension(new CssInlinerExtension());
            $env->addExtension(new IntlExtension());

            return $env;
        },

        Validator\SendRequest::class => static function (ContainerInterface $c): Validator\SendRequest {
            return new Validator\SendRequest(
                $c->get(Config::class),
                $c->get(Twig\Environment::class),
                $c->get('settings')['twig'],
            );
        },
    ]);
};

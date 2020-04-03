<?php

declare(strict_types=1);

use Mailer\Application\Actions\Send;
use Mailer\Application\Actions\Status;
use Mailer\Application\Auth\SendAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/ping', Status::class);

    $app->group('/v1', function (RouteCollectorProxy $versionGroup) {
        $versionGroup->post('/send', Send::class);
    })
        ->add(SendAuthMiddleware::class);
};

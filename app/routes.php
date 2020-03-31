<?php

declare(strict_types=1);

use Mailer\Application\Actions\Send;
use Mailer\Application\Actions\Status;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/ping', Status::class);

    $app->group('/v1', function (RouteCollectorProxy $versionGroup) {
        // TODO make a POST
        $versionGroup->get('/send', Send::class);

//            ->add(SendAuthMiddleware::class);// TODO auth for v1 group
    });
};

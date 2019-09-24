<?php

use Slim\Http\Request;
use Slim\Http\Response;

use App\TrelloManager;
// Routes

$app->get('/cron', TrelloManager::class . ':archiveDONE');


$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    // $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});



<?php

use Slim\Http\Request;
use Slim\Http\Response;

require __DIR__ . '/trello-manager.php';
// Routes

$app->get('/cron',function () {
    // cron
    $this->logger->info("Trello Manager /cron");
    header("Content-Type: text/plain"); // sortie en mode texte
    $TM = new src\trelloManager\TrelloManager( $this );
    $TM->archiveDONE();
    return;
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    // $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});



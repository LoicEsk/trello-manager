<?php

use Slim\Http\Request;
use Slim\Http\Response;

use App\TrelloManager;
use App\TrelloTools;
// Routes

$app->get('/cron', TrelloManager::class . ':doCron');


$app->get('/lists', function (Request $request, Response $response, array $args) {
    $trelloTools = new TrelloTools($this);
    $lists = $trelloTools->getLists();

    $newResponse = $response->withHeader('Content-type', 'application/json');
    $response->getBody()->write(json_encode($lists));
    return $response;
});



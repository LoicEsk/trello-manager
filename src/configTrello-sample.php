<?php
return [
    "trelloKey" =>       "Your Trello key",
    "trelloToken" =>     "Your Trello token",
    "idTableau" =>       "The id of the board you want to work",
    "idListToClean" =>   "The id of the list you want to clean",
    "idListWaiting" =>   "The id of the list you'rre wainting for response",
    "slack_webhook" =>   "Slack webhook URL to use for notifs"
];
return [
    "trelloKey" =>       "Your Trello key",
    "trelloToken" =>     "Your Trello token",
    "slack_webhook" =>   "Slack webhook URL to use for notifs"
    "archivage"     => [
        [ "liste" => "id de la liste à archiver", "delai" => 7 ],
        [ "liste" => "id de l'autre liste à archiver", "delai" => 15 ]
    ],
    "up"            => [
        ["liste" => "id de la liste à notifier", "delai" => 30 ]
    ]
];
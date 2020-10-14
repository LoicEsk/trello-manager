# trello-manager
Automatisation de taches et statistiques pour Trello

Application experimentale
Basée sur le framework Symfony

Lancement de taches automatisées :
``php bin/console app:run``
A lancer via un cron

## Installation

1. Cloner le dépôt
2. Installer les dépendances php : ``composer install``
3. Dans /src/ copier le ficher configTrello-sample.php en configTrello.php
4. Entrer les clés et les ID Trello dans fichier configTrello.php

## PHP build-in serveur

``Symfony server:start``

## Docker

``docker-compose up``

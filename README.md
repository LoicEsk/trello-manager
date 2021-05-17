# trello-manager
Automatisation de taches et statistiques pour Trello

Application experimentale
Basée sur le framework Symfony

Lancement de taches automatisées :
``php bin/console app:run``
A lancer via un cron

## Installation

1. Cloner le dépôt
2. Installer les dépendances php : `composer install`
3. Copier le .env en .env.local et modifier les informations de configuration (inutile dans l'environement de dev Docker inclu)
4. Créer la bdd. 
    a) Créer a table (trello_manager pour le dev Docker)
    b) Pour créer les tables : `php bin/console doctrine:schema:create`
5. Créer l'utilisateur admin
    a) Ajouter une entrée dans la table user. Pour le role, mettre *["ROLE_ADMIN"]*
    b) Utiliser la fonctionnalité *mot de passe perdu* pour générér le mdp, ou la commande `php bin/console security:encode-password` pour générer le hash du mdp que vous voulez
3. Entrer les clés et les ID Trello dans la table options avec les clés correspondantes. **Evolution prévue**

## PHP build-in serveur

``Symfony server:start``

## Docker

``docker-compose up``

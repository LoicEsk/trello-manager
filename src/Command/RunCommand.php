<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Option;

use App\Service\TrelloInterface;
use App\Service\CheckboxCommentator;

class RunCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:run';

    protected LoggerInterface $logger;
    protected EntityManagerInterface $em;

    protected OutputInterface $ouput;
    protected TrelloInterface $trello;

    protected String $trelloKey = '';
    protected String $trelloToken  = '';


    protected CheckboxCommentator $checkboxCommentator;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em, TrelloInterface $trello, CheckboxCommentator $checkboxCommentator)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->trello = $trello;
        $this->checkboxCommentator = $checkboxCommentator;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Lance un run d\'application')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Commande à lancer en cron')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        /**
         * Lecture parametres
         * Méthode horribe à changer
         */

        $oppArchivages = $this->em->getRepository( Option::class )->findByName( 'list_to_archive' );
        $this->archivageListe = array_map( function( $item ) {
            return json_decode( $item->getValue() );
        }, $oppArchivages );

        $oppUps = $this->em->getRepository( Option::class )->findByName( 'list_to_up' );
        $this->upListe = array_map( function( $item ) {
            return json_decode( $item->getValue() );
        }, $oppUps );


        $this->doArchivage();
        $this->doUp();
        $this->readNotifs();
        $this->checkboxCommentator->run( $output );


        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;
    }

    
    public function doArchivage() {
        $todo = $this->archivageListe;
        foreach( $todo as $tache ) {
            $this->archivage( $tache->liste, $tache->delai );
        }
    }

    public function doUp() {
        $body = $this->output;
        $todo = $this->upListe;
        foreach( $todo as $tache ) {
            $this->up( $tache->liste, $tache->delai );
        }
    }

    public function archivage( $idListe, $delai ) {
        $body = $this->output;
        $body->write("\n== Archivage des cartes $idListe\n");

        $timeNow = new \DateTime("NOW");
        $cards = $this->trello->getList( $idListe );
        $cardsCount = \is_array( $cards) ? count( $cards) : 0;
        $body->write("   " . $cardsCount . " cartes à archiver au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                $lastActivity = new \DateTime($card->dateLastActivity);
                $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;
                $body->write( " - Il y a " . round($deltaLastActivity, 2) . " jours : " . $card->name . "\n" );

                if($deltaLastActivity > $delai) {
                    if($this->trello->closeCard($card->id)) {
                        $msg = "ARCHIVEE : " . $card->name;
                        $this->logger->info($msg);
                    } 
                    else $this->logger->error( "La carte " . $card->name . " n'a pas pu être archivée");
                }
            }
        } else {
            $this->logger->error( "Liste à nettoyer introuvable :(");
        }
    }

    public function up( $idListe, $delai ) {
        $body = $this->output; // $body->write('somthing'); pour la sortie
        $body->write("\n== Rappel des cartes $idListe\n");

        $timeNow = new \DateTime("NOW");
        $cards = $this->trello->getList( $idListe );
        $cardsCount = \is_array( $cards) ? count( $cards) : 0;
        $body->write("   " . $cardsCount . " cartes à notifier au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                // dernière actuvuté de la carte
                $lastActivity = new \DateTime($card->dateLastActivity);
                $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;

                // dernière modification de carte
                $actionsUpdate = $this->trello->getCardActions( $card->id, "updateCard" );
                $d = new \DateTime(isset($actionsUpdate[0]->date) ? $actionsUpdate[0]->date : $card->dateLastActivity );
                $deltaUpdate = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours

                $cardLink = "https://www.trello.com/c/" . $card->shortLink;
                $body->write( "   - Carte en attente depuis " . round($deltaUpdate, 2) . " jours. Aucune activité depuis " . round($deltaLastActivity, 2) . " jours \t\t-- " . $card->name . "\n" );

                if($deltaLastActivity > $delai) {
                    $notif = "@card Cette carte est ici depuis " . round($deltaUpdate, 0) . " jours.";
                    if($deltaUpdate > 2.5 * $delai) $notif = "@card Cette carte attend ici depuis " . round($deltaUpdate, 0) . " jours.";
                    if($deltaUpdate > 4 * $delai) $notif = "@card Cette carte poirote ici depuis " . round($deltaUpdate, 0) . " jours. :scream:";
                    if($deltaUpdate > 7 * $delai) $notif = "@card " . round($deltaUpdate, 0) . " jours !! :cold_sweat:";
                    if($deltaUpdate > 8 * $delai) $notif = "@card " . round($deltaUpdate, 0) . " jours !! :dizzy_face:";
                    if($deltaUpdate > 10 * $delai) $notif = "@card " . round($deltaUpdate, 0) . " jours !! :skull:";
                    if($deltaUpdate > 11 * $delai) $notif = "@card " . round($deltaUpdate, 0) . " jours !! :skull_and_crossbones:";
                    
                    $body->write("=> UP !\n");
                    $this->logger->info( "UP : Ici depuis " . round($deltaUpdate, 2) . " jours. Aucune activité depuis " . round($deltaLastActivity, 2) . " jours : " . $card->name . "($cardLink)" );
                    $this->trello->sendComment( $card->id, $notif );
                }
            }
        } else {
            $this->logger->info(":danger: La liste $idListe est introuvable !");
        }
    }

    public function readNotifs( ) {
        $body = $this->output;
        $body->write( "\n== Réponses aux notifications\n" );

        $notifications = $this->trello->getUnreadNotifications();
        if( $notifications ) {
            $body->write( count( $notifications ) . " notifications\n" );
            foreach( $notifications as $notif ) {
                // var_dump( $notif );
                $notifId = $notif->id;
                $cardId = $notif->data->card->id;
                $senderSlug = $notif->memberCreator->username;
                $senderFullName = $notif->memberCreator->fullName;
                
                switch( random_int( 0, 5 ) ) {
                    case 0: $answer = ':horse:'; break;
                    case 1: $answer = ':collision:'; break;
                    case 2: $answer = ':innocent:'; break;
                    case 3: $answer = ':flushed:'; break;
                    case 4: $answer = ':wink:'; break;
                    case 5: $answer = 'https://gph.is/189r81H'; break;
                }
                // $this->logger->info( 'Notif data : ', $notif );
                $cardLink = "https://www.trello.com/c/" . $notif->data->card->shortLink;
                $this->logger->info( "Notif : Réponse à @$senderSlug -> $answer ($cardLink)" );
                $this->trello->sendComment( $cardId, $answer );
                $body->write( "Notif : Réponse à @$senderSlug -> $answer" );
                $this->trello->trelloRequest( "notifications/$notifId/unread", [ "value" => "false" ], "PUT" );
                // var_dump( $this->trello->trelloRequest( "notifications/$notifId", [ 'unread' => false ], "PUT" ) );
                // var_dump( $answer );
            }

        }
    }
}
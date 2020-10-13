<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class RunCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:run';

    protected String $trelloKey = '';
    protected String $trelloToken  = '';

    protected OutputInterface $ouput;
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

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
        
        $output->writeln([
            'RUN',
            '============'
        ]);

        /**
         * Lecture parametres
         * Méthode horribe à changer
         */
        $config = require ( __DIR__ . '/../configTrello.php');
        $this->trelloKey = $config["trelloKey"];
        $this->trelloToken = $config["trelloToken"];
        $this->archivageListe = $config['archivage'];
        $this->upListe = $config['up'];

        $this->doArchivage();
        $this->doUp();


        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;
    }

    
    public function doArchivage() {
        $todo = $this->archivageListe;
        foreach( $todo as $tache ) {
            $this->archivage( $tache['liste'], $tache['delai'] );
        }
    }

    public function doUp() {
        $body = $this->output;
        $todo = $this->upListe;
        foreach( $todo as $tache ) {
            $this->up( $tache['liste'], $tache['delai'] );
        }
    }

    public function archivage( $idListe, $delai ) {
        $body = $this->output;
        $body->write("\n== Archivage des cartes $idListe\n");

        $timeNow = new \DateTime("NOW");
        $cards = $this->getList( $idListe );
        $body->write("   " . count($cards) . " cartes à archiver au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                $lastActivity = new \DateTime($card->dateLastActivity);
                $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;
                $body->write( " - Il y a " . round($deltaLastActivity, 2) . " jours : " . $card->name . "\n" );

                if($deltaLastActivity > $delai) {
                    if($this->closeCard($card->id)) {
                        $msg = "ARCHIVEE : " . $card->name;
                        $this->app->logger->info($msg);
                        $this->logger->info($msg);
                    } 
                    else $this->app->logger->error( "La carte " . $card->name . " n'a pas pu être archivée");
                }
            }
        } else {
            $this->app->logger->error( "Liste à nettoyer introuvable :(");
        }
    }

    public function up( $idListe, $delai ) {
        $body = $this->output; // $body->write('somthing'); pour la sortie
        $body->write("\n== Rappel des cartes $idListe\n");

        $timeNow = new \DateTime("NOW");
        $cards = $this->getList( $idListe );
        $body->write("   " . count($cards) . " cartes à notifier au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                // dernière actuvuté de la carte
                $lastActivity = new \DateTime($card->dateLastActivity);
                $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;

                // dernière modification de carte
                $actionsUpdate = $this->getCardActions( $card->id, "updateCard" );
                $d = new \DateTime(isset($actionsUpdate[0]->date) ? $actionsUpdate[0]->date : $card->dateLastActivity );
                $deltaUpdate = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours

                $cardLink = "https://www.trello.com/c/" . $card->shortLink;
                $body->write( "Carte en attente depuis " . round($deltaUpdate, 2) . " jours. Aucune activité depuis " . round($deltaLastActivity, 2) . " jours \t\t-- " . $card->name . "\n" );

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
                    $this->sendComment( $card->id, $notif );
                }
            }
        } else {
            $this->app->logger->error( "Liste $idListe introuvable :(");
            $this->logger->info(":danger: La liste $idListe est introuvable !");
        }
    }

    public function readNotifs( ) {
        $body = $this->response->getBody();
        $body->write( "\n== Réponses aux notifications\n" );

        $notifications = $this->getUnreadNotifications();
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
                $this->sendComment( $cardId, $answer );
                $body->write( "Notif : Réponse à @$senderSlug -> $answer" );
                $this->trelloRequest( "notifications/$notifId/unread", [ "value" => "false" ], "PUT" );
                // var_dump( $this->trelloRequest( "notifications/$notifId", [ 'unread' => false ], "PUT" ) );
                // var_dump( $answer );
            }

        }
    }

    private function getList($listID) {
        $route = 'lists/' . $listID . '/cards';
        $rtn = $this->trelloRequest($route);
        if($rtn["status"] == 200) {
            $cards = json_decode($rtn["response"]);
            return $cards;
        } else {
            $this->putput->write( "Liste $listID introuvable :(\n" );
            return NULL;
        }
    }

    private function getCardActions( $card_id, $type ) {
        $reponse = $this->trelloRequest('cards/' . $card_id. '/actions', array( "filter" => $type ));
        if($reponse["status"] == 200) {
            $actions = json_decode($reponse["response"]);
            return $actions;
        } else {
            echo "Carte " . $card_id ." est introuvable\n";
            return NULL;
        }
    }

    private function getUnreadNotifications( ) {
        $reponse = $this->trelloRequest( 'members/me/notifications', [ 'read_filter' => 'unread'] );
        if($reponse["status"] == 200) {
            $actions = json_decode($reponse["response"]);
            return $actions;
        } else {
            echo "Notifications introuvables\n";
            return NULL;
        }
    }

    private function closeCard($card_id) {
        $rtn = $this->trelloRequest("cards/" . $card_id, array("closed" => true), "PUT");
        return $rtn["status"] == 200;
    }

    private function sendComment( $card_id, $comment_text ) {
        $rtn = $this->trelloRequest("cards/$card_id/actions/comments", array("text" => $comment_text), "POST");
        // var_dump($rtn);
        return $rtn["status"] == 200;
    }

    private function trelloRequest($cmd, $params = array(), $methode = "GET") {
        // url de l'API Trello
        $url = 'https://api.trello.com/1/' . $cmd;

        // ajout des identifiants
        $params['key'] = $this->trelloKey;
        $params['token'] = $this->trelloToken;

        $paramsStr = implode('&', array_map(
            function ($v, $k) {
                if(is_array($v)){
                    return $k.'[]='.implode('&'.$k.'[]=', $v);
                }else{
                    return $k.'='.$v;
                }
            }, 
            $params, 
            array_keys($params)
        ));

        if( $methode !== "POST" ) $url .= "?" . $paramsStr;

        // echo 'Envoi d\'une requête : ', $url, "\n";

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methode);
        if( $methode === "POST" ) curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
        $body = curl_exec($ch); 
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch); 

        $retour = array(
            'status' => $httpCode,
            'response' => $body
        );
        return $retour;
    }

}
<?php

namespace App;

use \Datetime;
use Slim\Http\Request;
use Slim\Http\Response;

class TrelloManager {
    private $trelloKey = '';
    private $trelloToken  = '';
    private $idTableau = '';
    private $idListToClean = '';
    private $app = null;

    protected $request = null;
    protected $response = null;
    

    public function __construct($container) {

        $this->app = $container;
        // var_dump($this->app['settings']);
        
        $config = $this->app['settings']['trello'];
        // var_dump($config);
        $this->trelloKey = $config["trelloKey"];
        $this->trelloToken = $config["trelloToken"];
        $this->slack_webhook = $config["slack_webhook"];
    }

    public function doCron(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response->withHeader('Content-type', 'text/plain');

        $this->doArchivage();
        $this->doUp();

        $body = $this->response->getBody();
        $body->write("\n == What's all folks ! ==");

        return $this->response;
    }

    public function doArchivage() {
        $body = $this->response->getBody();
        $todo = $this->app['settings']['trello']['archivage'];
        foreach( $todo as $tache ) {
            $this->archivage( $tache['liste'], $tache['delai'] );
        }
    }

    public function doUp() {
        $body = $this->response->getBody();
        $todo = $this->app['settings']['trello']['up'];
        foreach( $todo as $tache ) {
            $this->up( $tache['liste'], $tache['delai'] );
        }
    }

    public function archivage( $idListe, $delai ) {
        $body = $this->response->getBody(); // $body->write('something'); pour la sortie
        $body->write("\n== Archivage des cartes $idListe\n");

        $timeNow = new DateTime("NOW");
        $cards = self::getList( $idListe );
        $body->write("   " . count($cards) . " cartes à archiver au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                // $lastActivity = new DateTime($card->dateLastActivity);
                // $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;
                // $body->write("Dernière activité il y a " . round($deltaLastActivity, 2));

                $actions = self::getCardActions( $card->id, "updateCard" );
                if($actions) {
                    foreach($actions as $action) {
                        if(isset($action->data->listAfter) && $action->data->listAfter) {
                            $d = new DateTime($action->date);
                            // $since = $d->diff($timeNow, true);
                            // echo "Il y a ", $since->format('%a days and %h'), "->", $delta, " jours", "\n";
                            $delta = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours
                            $body->write( " - Il y a " . round($delta, 2) . " jours : " . $card->name . "\n" );
                            if($delta > $delai) {
                                if(self::closeCard($card->id)) {
                                    $msg = "La carte " . $card->name . " a été archivée";
                                    $this->app->logger->info($msg);
                                    self::sendToSlack($msg);
                                } 
                                else $this->app->logger->info( "La carte " . $card->name . " n'a pas pu être archivée");
                            }
                            break;
                        }
                    }
                }
            }
        } else {
            $this->app->logger->error( "Liste à nettoyer introuvable :(");
        }
    }

    public function up( $idListe, $delai ) {
        $body = $this->response->getBody(); // $body->write('somthing'); pour la sortie
        $body->write("\n== Rappel des cartes $idListe\n");

        $timeNow = new DateTime("NOW");
        $cards = self::getList( $idListe );
        $body->write("   " . count($cards) . " cartes à notifier au bout de $delai jours\n");
        if($cards) {
            foreach($cards as $card) {
                // dernière actuvuté de la carte
                $lastActivity = new DateTime($card->dateLastActivity);
                $deltaLastActivity = ($timeNow->getTimestamp() - $lastActivity->getTimestamp()) / 86400;

                // dernière modification de carte
                $actionsUpdate = self::getCardActions( $card->id, "updateCard" );
                $d = new DateTime(isset($actionsUpdate[0]->date) ? $actionsUpdate[0]->date : "NOW" );
                $deltaUpdate = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours

                $body->write( "Carte en attente depuis " . round($deltaUpdate, 2) . " jours. Aucune activité depuis " . round($deltaLastActivity, 2) . " jours \t\t-- " . $card->name . "\n" );

                if($deltaLastActivity > $delai) {
                    $notif = "@card Cette carte est ici depuis " . round($deltaUpdate, 0) . " jours. Il faudrait songer à s'en occuper.";
                    $body->write("=> UP !\n");
                    self::sendToSlack( "UP : La carte " . $card->name . " pourrie depuis " . round($deltaUpdate, 2) . ". Aucune réponse depuis " . round($deltaLastActivity, 2) . ".");
                    $this->sendComment( $card->id, $notif );
                }
            }
        } else {
            $this->app->logger->error( "Liste en attente introuvable :(");
        }
    }

    private function getList($listID) {
        $route = 'lists/' . $listID . '/cards';
        $rtn = self::trelloRequest($route);
        if($rtn["status"] == 200) {
            $cards = json_decode($rtn["response"]);
            return $cards;
        } else {
            $this->response->getBody()->write( "Liste $listID introuvable :(\n" );
            return NULL;
        }
    }

    private function getCardActions( $card_id, $type ) {
        $reponse = self::trelloRequest('cards/' . $card_id. '/actions', array( "filter" => $type ));
        if($reponse["status"] == 200) {
            $actions = json_decode($reponse["response"]);
            return $actions;
        } else {
            echo "Carte " . $card->id ." est introuvable\n";
            return NULL;
        }
    }

    private function closeCard($card_id) {
        $rtn = self::trelloRequest("cards/" . $card_id, array("closed" => true), "PUT");
        return $rtn["status"] == 200;
    }

    private function sendComment( $card_id, $comment_text ) {
        $rtn = self::trelloRequest("cards/$card_id/actions/comments", array("text" => $comment_text), "POST");
        var_dump($rtn);
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

    private function sendToSlack($msg) {
        $url = $this->slack_webhook;

        $message = array('payload' => json_encode(array(
            'text' => 'Trello-Manager : ' . $msg
        )));

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
        $body = curl_exec($ch); 
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch); 

        $retour = array(
            'status' => $httpCode,
            'response' => $body
        );
        return $retour['response'] == 'ok';
    }

}
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

    public function __construct($container) {

        $this->app = $container;
        // var_dump($this->app['settings']);
        
        $config = $this->app['settings']['trello'];
        // var_dump($config);
        $this->trelloKey = $config["trelloKey"];
        $this->trelloToken = $config["trelloToken"];
        $this->idTableau = $config["idTableau"];
        $this->idListToClean = $config["idListToClean"];
        $this->idListWaiting = $config["idListWaiting"];
        $this->slack_webhook = $config["slack_webhook"];
    }

    public function doCron(Request $request, Response $response) {
        // ARCHIVAGE DES cartes dans DONE depuis assez longtemps
        $newResponse = $response->withHeader('Content-type', 'text/plain');
        $this->archiveDONE($request, $newResponse);
        $this->upATTENTE($request, $newResponse);

        return $newResponse;
    }

    public function archiveDONE(Request $request, Response $response) {
        $body = $response->getBody(); // $body->write('somthing'); pour la sortie
        $body->write("\n== Archivage des cartes DONE\n");

        $timeNow = new DateTime("NOW");
        $cards = self::getList( $this->idListToClean, $response );
        $body->write("   " . count($cards) . " cartes dans EN ATTENTE\n");
        if($cards) {
            foreach($cards as $card) {
                $actions = self::getCardActions( $card->id );
                if($actions) {
                    foreach($actions as $action) {
                        if(isset($action->data->listAfter) && $action->data->listAfter) {
                            $d = new DateTime($action->date);
                            // $since = $d->diff($timeNow, true);
                            // echo "Il y a ", $since->format('%a days and %h'), "->", $delta, " jours", "\n";
                            $delta = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours
                            $body->write( "Il y a " . round($delta, 2) . " jours : " . $card->name . "\n" );
                            if($delta > 7) {
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

    public function upATTENTE(Request $request, Response $response) {
        $body = $response->getBody(); // $body->write('somthing'); pour la sortie
        $body->write("\n== Rappel des cartes EN ATTENTE\n");

        $timeNow = new DateTime("NOW");
        $cards = self::getList( $this->idListWaiting, $response );
        $body->write("   " . count($cards) . " cartes dans EN ATTENTE\n");
        if($cards) {
            foreach($cards as $card) {
                $actions = self::getCardActions( $card->id );
                if($actions) {
                    foreach($actions as $action) {
                        if(isset($action->data->listAfter) && $action->data->listAfter) {
                            $d = new DateTime($action->date);
                            // $since = $d->diff($timeNow, true);
                            // echo "Il y a ", $since->format('%a days and %h'), "->", $delta, " jours", "\n";
                            $delta = ($timeNow->getTimestamp() - $d->getTimestamp()) / 86400; // différence en jours
                            $body->write( "Depuis " . round($delta, 2) . " jours : " . $card->name . "\n" );
                            if($delta > 15) {
                                $body->write("  => up !");
                            }
                            break;
                        }
                    }
                }
            }
        } else {
            $this->app->logger->error( "Liste en attente introuvable :(");
        }
    }

    private function getList($listID, Response $response) {
        $route = 'lists/' . $listID . '/cards';
        $rtn = self::trelloRequest($route);
        if($rtn["status"] == 200) {
            $cards = json_decode($rtn["response"]);
            return $cards;
        } else {
            $response->getBody()->write( "Liste $listID introuvable :(\n" );
            return NULL;
        }
    }

    private function getCardActions( $card_id ) {
        $reponse = self::trelloRequest('cards/' . $card_id. '/actions', array( "filter" => "updateCard" ));
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

        $url .= "?" . $paramsStr;

        // echo 'Envoi d\'une requête : ', $url, "\n";

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methode);
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
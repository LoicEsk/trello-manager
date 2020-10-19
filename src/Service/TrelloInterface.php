<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Option;

class TrelloInterface {

    protected LoggerInterface $logger;
    protected EntityManagerInterface $em;

    protected String $trelloKey = '';
    protected String $trelloToken = '';

    public function __construct( LoggerInterface $logger, EntityManagerInterface $em ) {
        $this->logger = $logger;
        $this->em =$em;

        $oppTrelloKey = $this->em->getRepository( Option::class )->findOneByName( 'trello_key' );
        if( $oppTrelloKey ) $this->trelloKey = $oppTrelloKey->getValue();

        $oppTrelloToken = $this->em->getRepository( Option::class )->findOneByName( 'trello_token' );
        if( $oppTrelloToken ) $this->trelloToken = $oppTrelloToken->getValue();
    }

    public function getAllMyBoards() {
        $rep = $this->trelloRequest( '/members/me/boards' );
        if( $rep[ 'status' ] !== 200 ) {
            $this->logger->warning( 'Liste des tableau introuvable' );
            return [];
        }
        return json_decode( $rep['response'] ) ;
    }

    public function closeCard($card_id) {
        $rtn = $this->trelloRequest("cards/" . $card_id, array("closed" => true), "PUT");
        return $rtn["status"] == 200;
    }

    public function getUnreadNotifications( ) {
        $reponse = $this->trelloRequest( 'members/me/notifications', [ 'read_filter' => 'unread'] );
        if($reponse["status"] == 200) {
            $actions = json_decode($reponse["response"]);
            return $actions;
        } else {
            echo "Notifications introuvables\n";
            return NULL;
        }
    }

    public function getCardActions( $card_id, $type ) {
        $reponse = $this->trelloRequest('cards/' . $card_id. '/actions', array( "filter" => $type ));
        if($reponse["status"] == 200) {
            $actions = json_decode($reponse["response"]);
            return $actions;
        } else {
            echo "Carte " . $card_id ." est introuvable\n";
            return NULL;
        }
    }

    public function getList($listID) {
        $route = 'lists/' . $listID . '/cards';
        $rtn = $this->trelloRequest($route);
        if($rtn["status"] == 200) {
            $cards = json_decode($rtn["response"]);
            return $cards;
        } else {
            echo  "Liste $listID introuvable :(\n";
            $this->logger->warning( "Liste $listID introuvable :(\n" );
            return NULL;
        }
    }

    public function sendComment( $card_id, $comment_text ) {
        echo "Commentaire sur la carte $card_id >> $comment_text\n";
        if( $_ENV['APP_ENV'] !== 'prod' ) {
            $rtn = $this->trelloRequest("cards/$card_id/actions/comments", array("text" => $comment_text), "POST");
            return $rtn["status"] == 200;
        }
        echo "!! DEV MODE -> non envoyé !!";
        return true;
        // var_dump($rtn);
    }

    public function trelloRequest($cmd, $params = array(), $methode = "GET") {
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
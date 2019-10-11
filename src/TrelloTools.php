<?php

namespace App;

use \Datetime;
use Slim\Http\Request;
use Slim\Http\Response;

class TrelloTools {

    protected $app = null;
    protected $trelloKey = "";
    protected $trelloToken = "";

    public function __construct($container) {
        $this->app = $container;

        $config = $this->app['settings']['trello'];
        $this->trelloKey = $config["trelloKey"];
        $this->trelloToken = $config["trelloToken"];
    }

    public function getLists() {
        $idTableau = $this->app['settings']['trello']['idTableau'];
        $apiResponse = $this->trelloRequest("boards/$idTableau/lists");
        if($apiResponse["status"] == 200) {
            return $apiResponse['response'];
        }
        return null;
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

}
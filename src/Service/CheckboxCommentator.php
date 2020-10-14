<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

use App\Service\TrelloInterface;

use App\Entity\Option;

class CheckboxCommentator {

    protected LoggerInterface $logger;
    protected EntityManagerInterface $em;
    protected TrelloInterface $trello;
    protected $output;

    public function __construct( LoggerInterface $logger, EntityManagerInterface $em, TrelloInterface $trello ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->trello = $trello;
    }

    public function run( $output ) {
        $this->output = $output;

        $this->output->writeln( [
            '',
            '== Notifications des checkboxes cochées'
        ]);
        $oppCheckboxCommentBoards = $this->em->getRepository( Option::class )->findByName( 'checkboxComment_board' );
        // $boards = $this->trello->getAllMyBoards();
        $boards = array_map( function( $opp ) {
            return $opp->getValue();
        }, $oppCheckboxCommentBoards );
        foreach( $boards as $board_id )
        {
            $this->output->writeln( '- Traitement du tableau ' . $board_id );
            $this->commentCheckboxOnBoad( $board_id );
        };

    }

    protected function commentCheckboxOnBoad( $board_id ) {

        $datePassage = new \DateTime( 'NOW', new \DateTimeZone( 'UTC' ) );

        // date depuis le dernier passage
        $oppDateLimite = $this->em->getRepository( Option::class )->findOneByName( 'checkbox_since_' . $board_id );
        if( is_null( $oppDateLimite ) ) {
            $oppDateLimite = new Option( 'checkbox_since_' . $board_id );
            $oppDateLimite->setValue( $datePassage->format( 'Y-m-d\TH:i:s\Z' ) );
            $this->em->persist( $oppDateLimite );
        }
        $dateLimiteStr = $oppDateLimite->getValue();

        $oppDateLimite->setValue( $datePassage->format( 'Y-m-d\TH:i:s\Z' ) );

        $rep = $this->trello->trelloRequest( "/boards/$board_id/actions", [ 'filter' => 'updateCheckItemStateOnCard', 'since' => $dateLimiteStr ] );
        if( $rep['status'] !== 200 ) {
            $this->logger->warning( "Echec de la lecture des actions du tableau $board_id" );
            return false;
        }

        $checkItemsDone = [];
        $notifs = [];
        foreach( json_decode( $rep['response'] ) as $action ) {
            // var_dump( $action ); die();
            $auteur_id = $action->idMemberCreator;
            $auteurName = $action->memberCreator->username;
            $card_id = $action->data->card->id;
            $checkItem = $action->data->checkItem; // id, name, state
            $checkList = $action->data->checklist; // id, name

            // if( in_array( "$checkList_$checkItem" ,$checkItemsDone ) ) continue;

            // $actionItem = $checkItem->state === "complete" ? "terminé" : "décoché";
            // $this->output->writeln( "    -  $action->date - $auteurName a $actionItem $checkItem->name dans $checkList->name");

            if( !isset( $notifs[$checkList->id] ) ) $notifs[$checkList->id] = [
                'name'  => str_replace( '*', '', $checkList->name ),
                'card_id'   => $card_id
            ];
            if( !isset( $notifs[$checkList->id]['items'][$checkItem->id] ) ) $notifs[$checkList->id]['items'][$checkItem->id] = [
                'name'  => str_replace( '*', '', $checkItem->name )
            ];
            $notifs[$checkList->id]['items'][$checkItem->id]['actions'][] = [
                'user_id'  => $action->idMemberCreator,
                'userName'  => $action->memberCreator->fullName,
                'state'     => $action->data->checkItem->state
            ];
        }

        // var_dump( $notifs );
        foreach( $notifs as $checklist => $dataList ) {
            foreach( $dataList['items'] as $checkItem => $dataItem ) {
                $comments = [];
                $commentsCount = 0;
                while( !empty( $dataItem['actions'] ) ) {
                    $action = array_pop( $dataItem['actions'] );
                    $commentsCount++;
                    $actionBulet = $action['state'] === 'complete' ? ':white_check_mark:' : ':white_medium_square:';
                    if( $commentsCount === 1 ) {
                        $actionText = $action['state'] === 'complete' ? "terminé" : "décoché";
                        $comments[] = "$actionBulet " . $action['userName'] . " a $actionText **" . $dataItem['name'] . "**";
                    }
                    else {
                        $actionText = $action['state'] === "complete" ? "coché" : "décoché";
                        $comments[] = "$actionBulet puis" . $action['userName'] . " l'a $actionText";
                    }
                }
                $comment = implode( "\n", $comments);
                $this->output->writeln( "\t- $comment" );
                $this->trello->sendComment( $dataList['card_id'], $comment );
            }
        }

        $this->em->flush();
    }
}
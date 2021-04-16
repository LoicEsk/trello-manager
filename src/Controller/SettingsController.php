<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use App\Service\TrelloInterface;


class SettingsController extends AbstractController
{
    /**
     * @Route("/settings", name="settings")
     */
    public function index( TrelloInterface $trello )
    {
        // liste des tableaux dispo
        $boards = $trello->getAllMyBoards();
        $boradList = array_map( function( $item ) {
            return [
                'name'      => $item->name,
                'id'        => $item->id
            ];
        }, $boards );
        return $this->render('settings/index.html.twig', [
            'controller_name' => 'SettingsController',
            'boards'        => $boradList
        ]);
    }
}

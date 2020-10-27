<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use App\Service\TrelloInterface;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
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
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'boards'        => $boradList
        ]);
    }
}

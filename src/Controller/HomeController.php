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
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}

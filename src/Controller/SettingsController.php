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
    public function index( )
    {

        return $this->render('settings/index.html.twig', [

        ]);
    }
}

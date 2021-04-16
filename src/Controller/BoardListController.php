<?php

namespace App\Controller;

use App\Entity\BoardList;
use App\Entity\Board;
use App\Form\BoardListType;
use App\Form\NewBoardListType;
use App\Repository\BoardListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TrelloInterface;


class BoardListController extends AbstractController
{
    /**
     * @Route("/lists", name="board_list_index", methods={"GET"})
     */
    public function index( BoardListRepository $boardListRepository): Response
    {
        return $this->render('board_list/index.html.twig', [
            'board_lists' => $boardListRepository->findAll(),
        ]);
    }

    /**
     * @Route("/board/{id}/list/new", name="board_list_new", methods={"GET","POST"})
     */
    public function new(Request $request, Board $board, TrelloInterface $trello): Response
    {
        
        $form = $this->createForm(NewBoardListType::class, null, [ 'board' => $board ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // données formulaire
            $data = $form->getData();
            $listID = $data['listID'];

            // récupération infos depuis Trello
            $listData = $trello->getList( $listID );
            // var_dump( $listData );
            
            // création de l'objet Board
            $boardList = new BoardList();
            $boardList->setIdTrello( $listID );
            $boardList->setName( $listData->name );
            $boardList->setBoard( $board );

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($boardList);
            $entityManager->flush();

            return $this->redirectToRoute('board_show', [ 'id' => $board->getId() ] );
        }

        return $this->render('board_list/new.html.twig', [
            'board'     => $board,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/lists/{id}", name="board_list_show", methods={"GET"})
     */
    public function show(BoardList $boardList): Response
    {
        return $this->render('board_list/show.html.twig', [
            'board_list' => $boardList,
            'board'     => $boardList->getBoard()
        ]);
    }

    /**
     * @Route("/lists/{id}/edit", name="board_list_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, BoardList $boardList): Response
    {
        $form = $this->createForm(BoardListType::class, $boardList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('board_list_index');
        }

        return $this->render('board_list/edit.html.twig', [
            'board_list' => $boardList,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/lists/{id}", name="board_list_delete", methods={"DELETE"})
     */
    public function delete(Request $request, BoardList $boardList): Response
    {
        if ($this->isCsrfTokenValid('delete'.$boardList->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($boardList);
            $entityManager->flush();
        }

        return $this->redirectToRoute('board_list_index');
    }
}

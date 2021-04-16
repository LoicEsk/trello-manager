<?php

namespace App\Controller;

use App\Entity\BoardList;
use App\Form\BoardListType;
use App\Repository\BoardListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/board/list")
 */
class BoardListController extends AbstractController
{
    /**
     * @Route("/", name="board_list_index", methods={"GET"})
     */
    public function index(BoardListRepository $boardListRepository): Response
    {
        return $this->render('board_list/index.html.twig', [
            'board_lists' => $boardListRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="board_list_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $boardList = new BoardList();
        $form = $this->createForm(BoardListType::class, $boardList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($boardList);
            $entityManager->flush();

            return $this->redirectToRoute('board_list_index');
        }

        return $this->render('board_list/new.html.twig', [
            'board_list' => $boardList,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="board_list_show", methods={"GET"})
     */
    public function show(BoardList $boardList): Response
    {
        return $this->render('board_list/show.html.twig', [
            'board_list' => $boardList,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="board_list_edit", methods={"GET","POST"})
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
     * @Route("/{id}", name="board_list_delete", methods={"DELETE"})
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

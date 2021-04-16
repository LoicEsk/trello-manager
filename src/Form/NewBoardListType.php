<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Service\TrelloInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Board;

class NewBoardListType extends AbstractType
{
    protected TrelloInterface $trello;

    public function __construct( TrelloInterface $trello ){
        $this->trello = $trello;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $board = $options['board'];

        $allLists = $this->trello->getListsFromBoard( $board->getIdTrello() );
        $boardLists = [];
        if( !$allLists ) $boardLists['ERREUR'] = 0;
        else {
            foreach( $allLists as $l ) {
                $boardLists[$l->name] = $l->id;
            }
        }
        $builder
            ->add('listID', ChoiceType::class, [
                'choices'  => $boardLists
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
            'board' => new Board
        ]);
    }
}

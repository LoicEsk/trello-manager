<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Service\TrelloInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class NewBoardType extends AbstractType
{
    protected TrelloInterface $trello;

    public function __construct( TrelloInterface $trello ){
        $this->trello = $trello;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $allBoards = $this->trello->getAllMyBoards();
        $boardList = [];
        foreach( $allBoards as $b ) {
            $boardList[$b->name] = $b->id;
        }
        $builder
            ->add('idBoard', ChoiceType::class, [
                'choices'  => $boardList
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}

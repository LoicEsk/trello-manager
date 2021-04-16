<?php

namespace App\Entity;

use App\Repository\BoardListRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoardListRepository::class)
 */
class BoardList
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=30)
     */
    private $idTrello;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity=Board::class, inversedBy="boardLists")
     * @ORM\JoinColumn(nullable=false)
     */
    private $board;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdTrello(): ?string
    {
        return $this->idTrello;
    }

    public function setIdTrello(string $idTrello): self
    {
        $this->idTrello = $idTrello;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBoard(): ?Board
    {
        return $this->board;
    }

    public function setBoard(?Board $board): self
    {
        $this->board = $board;

        return $this;
    }
}

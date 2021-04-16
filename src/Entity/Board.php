<?php

namespace App\Entity;

use App\Repository\BoardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoardRepository::class)
 */
class Board
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
     * @ORM\OneToMany(targetEntity=BoardList::class, mappedBy="board", orphanRemoval=true)
     */
    private $boardLists;

    public function __construct()
    {
        $this->boardLists = new ArrayCollection();
    }

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

    /**
     * @return Collection|BoardList[]
     */
    public function getBoardLists(): Collection
    {
        return $this->boardLists;
    }

    public function addBoardList(BoardList $boardList): self
    {
        if (!$this->boardLists->contains($boardList)) {
            $this->boardLists[] = $boardList;
            $boardList->setBoard($this);
        }

        return $this;
    }

    public function removeBoardList(BoardList $boardList): self
    {
        if ($this->boardLists->contains($boardList)) {
            $this->boardLists->removeElement($boardList);
            // set the owning side to null (unless already changed)
            if ($boardList->getBoard() === $this) {
                $boardList->setBoard(null);
            }
        }

        return $this;
    }
}

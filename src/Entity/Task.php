<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TaskRepository::class)
 */
class Task
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;
    
    /**
     * @ORM\ManyToOne(targetEntity=Board::class, inversedBy="tasks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $board;
    
    /**
     * @ORM\ManyToOne(targetEntity=BoardList::class, inversedBy="tasks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $boardList;
    
    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $properties = [];
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

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

    public function getBoardList(): ?BoardList
    {
        return $this->boardList;
    }

    public function setBoardList(?BoardList $boardList): self
    {
        $this->boardList = $boardList;

        return $this;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;

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
}

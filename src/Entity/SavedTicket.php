<?php

namespace App\Entity;

use App\Repository\SavedTicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SavedTicketRepository::class)]
#[ORM\Table(name: 'saved_ticket')]
#[ORM\UniqueConstraint(name: 'uniq_saved_ticket_ticket', columns: ['ticket'])]
class SavedTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $ticket = '';

    #[ORM\Column(length: 64)]
    private string $jobNumber = '';

    #[ORM\Column(length: 255)]
    private string $description = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): string
    {
        return $this->ticket;
    }

    public function setTicket(string $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getJobNumber(): string
    {
        return $this->jobNumber;
    }

    public function setJobNumber(string $jobNumber): self
    {
        $this->jobNumber = $jobNumber;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

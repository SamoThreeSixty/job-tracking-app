<?php

namespace App\DTO;

class TicketDto
{
    private string $ticket;
    private string $jobNumber;
    private string $description;

    public function __construct(string $ticket, string $jobNumber, string $description)
    {
        $this->ticket = $ticket;
        $this->jobNumber = $jobNumber;
        $this->description = $description;
    }

    public function getTicket(): string
    {
        return $this->ticket;
    }

    public function getJobNumber(): string
    {
        return $this->jobNumber;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}

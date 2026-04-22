<?php

namespace App\Contracts;

use App\DTO\TicketDto;

interface TicketSourceParserInterface
{
    /**
     * @return TicketDto[]
     */
    public function parse(string $html): array;
}

<?php

namespace App\Service;

use App\Contracts\TicketSourceParserInterface;
use App\DTO\TicketDto;

final class TicketSourceParser implements TicketSourceParserInterface
{
    /**
     * @return list<array{ticket: string, jobNumber: string, description: string}>
     */
    public function parse(string $html): array
    {
        $html = trim($html);
        if ('' === $html) {
            return [];
        }

        libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $livePanel = $xpath->query("//*[@id='live']")->item(0);
        if (!$livePanel instanceof \DOMElement) {
            return [];
        }

        foreach ($xpath->query('.//table', $livePanel) ?: [] as $table) {
            if (!$table instanceof \DOMElement) {
                continue;
            }

            $tickets = $this->extractFromTable($xpath, $table);
            if ([] !== $tickets) {
                return array_map(function ($ticket) {
                    return new TicketDto(
                        $ticket['ticket'],
                        $ticket['jobNumber'],
                        $ticket['description']
                    );
                }, $tickets);
            }
        }

        return [];
    }

    /**
     * @return list<array{ticket: string, jobNumber: string, description: string}>
     */
    private function extractFromTable(\DOMXPath $xpath, \DOMElement $table): array
    {
        $rows = $xpath->query('.//tr', $table);
        if (false === $rows || 0 === $rows->length) {
            return [];
        }

        $headerCells = null;
        $headerRow = null;

        foreach ($rows as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }

            $thCells = $xpath->query('./th', $row);
            if (false !== $thCells && $thCells->length > 0) {
                $headerCells = $thCells;
                $headerRow = $row;
                break;
            }
        }

        if (null === $headerCells || null === $headerRow) {
            return [];
        }

        $headers = [];
        foreach ($headerCells as $cell) {
            $headers[] = $this->normalize($cell->textContent ?? '');
        }

        $ticketIndex = $this->findHeaderIndex($headers, ['ticket']);
        $jobIndex = $this->findHeaderIndex($headers, ['job no']);
        $descriptionIndex = $this->findHeaderIndex($headers, ['subject']);

        if (-1 === $ticketIndex || -1 === $jobIndex) {
            return [];
        }

        $tickets = [];

        foreach ($rows as $row) {
            if (!$row instanceof \DOMElement || $row->isSameNode($headerRow)) {
                continue;
            }

            $cells = $xpath->query('./td', $row);
            if (false === $cells || 0 === $cells->length) {
                continue;
            }

            $values = [];
            foreach ($cells as $cell) {
                $values[] = trim(preg_replace('/\s+/', ' ', $cell->textContent ?? '') ?? '');
            }

            if (!isset($values[$ticketIndex], $values[$jobIndex])) {
                continue;
            }

            $ticket = $values[$ticketIndex];
            $jobNumber = $values[$jobIndex];
            $description = $descriptionIndex >= 0 ? ($values[$descriptionIndex] ?? '') : '';

            if ('' === $ticket || '' === $jobNumber) {
                continue;
            }

            $tickets[] = [
                'ticket' => $ticket,
                'jobNumber' => $jobNumber,
                'description' => $description,
            ];
        }

        return $tickets;
    }

    /**
     * @param list<string> $headers
     * @param list<string> $keywords
     */
    private function findHeaderIndex(array $headers, array $keywords): int
    {
        foreach ($headers as $index => $header) {
            foreach ($keywords as $keyword) {
                if (str_contains($header, $keyword)) {
                    return $index;
                }
            }
        }

        return -1;
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}

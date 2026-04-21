<?php

namespace App\Service;

final class QuarterHourRounder
{
    public function floor(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $this->round($dateTime, 'floor');
    }

    public function ceil(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $this->round($dateTime, 'ceil');
    }

    public function normalizeForManualEntry(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        $minutes = (int) $dateTime->format('i');
        $remainder = $minutes % 15;

        if (0 === $remainder) {
            return $dateTime->setTime(
                (int) $dateTime->format('H'),
                $minutes,
                0
            );
        }

        if ($remainder < 8) {
            return $this->floor($dateTime);
        }

        return $this->ceil($dateTime);
    }

    private function round(\DateTimeImmutable $dateTime, string $mode): \DateTimeImmutable
    {
        $hour = (int) $dateTime->format('H');
        $minutes = (int) $dateTime->format('i');
        $remainder = $minutes % 15;

        if (0 === $remainder) {
            return $dateTime->setTime($hour, $minutes, 0);
        }

        if ('floor' === $mode) {
            return $dateTime->setTime($hour, $minutes - $remainder, 0);
        }

        $adjusted = $dateTime->modify(sprintf('+%d minutes', 15 - $remainder));

        return $adjusted->setTime(
            (int) $adjusted->format('H'),
            (int) $adjusted->format('i'),
            0
        );
    }
}

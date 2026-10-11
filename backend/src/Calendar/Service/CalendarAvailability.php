<?php

declare(strict_types=1);

namespace App\Calendar\Service;

use App\Calendar\Entity\AdventDayProgress;

final class CalendarAvailability
{
    private \DateTimeZone $timezone;

    public function __construct(string $businessTimezone)
    {
        $this->timezone = new \DateTimeZone($businessTimezone);
    }

    public function currentYear(\DateTimeImmutable $now): int
    {
        return (int) $now->setTimezone($this->timezone)->format('Y');
    }

    public function availableOn(int $year, int $day): string
    {
        return sprintf('%04d-12-%02d', $year, $day);
    }

    public function status(AdventDayProgress $progress, \DateTimeImmutable $now): string
    {
        if ($progress->getCompletedAt() !== null) return 'completed';
        if ($progress->getCalendar()->getUser()->isTestUser()) return 'available';
        $today = $now->setTimezone($this->timezone)->format('Y-m-d');
        $availableOn = $this->availableOn($progress->getCalendar()->getYear(), $progress->getDay());
        if ($today < $availableOn) return 'locked';
        $lastAvailableOn = (new \DateTimeImmutable($availableOn, $this->timezone))->modify('+2 days')->format('Y-m-d');
        return $today <= $lastAvailableOn ? 'available' : 'expired';
    }
}

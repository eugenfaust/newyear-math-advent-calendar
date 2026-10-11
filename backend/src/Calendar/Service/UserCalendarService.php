<?php

declare(strict_types=1);

namespace App\Calendar\Service;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Task\Storage\ImageStorage;
use App\User\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class UserCalendarService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarAvailability $availability,
        private ImageStorage $images,
    ) {}

    public function summary(User $user, int $year, \DateTimeImmutable $now): array
    {
        $calendar = $this->calendar($user, $year);
        $progresses = $this->em->getRepository(AdventDayProgress::class)->findBy(['calendar' => $calendar], ['day' => 'ASC']);
        $days = [];
        $completed = 0;
        $unlocked = 0;
        foreach ($progresses as $progress) {
            $status = $this->availability->status($progress, $now);
            if ($status === 'completed') $completed++;
            if ($status !== 'locked') $unlocked++;
            $days[] = [
                'day' => $progress->getDay(),
                'status' => $status,
                'availableOn' => $this->availability->availableOn($year, $progress->getDay()),
                'openedAt' => $this->utc($progress->getOpenedAt()),
                'completedAt' => $this->utc($progress->getCompletedAt()),
            ];
        }
        return [
            'uuid' => $calendar->getUuid(), 'year' => $year,
            'startedAt' => $this->utc($calendar->getStartedAt()),
            'completedDays' => $completed, 'availableDays' => $unlocked,
            'totalDays' => 31, 'days' => $days,
        ];
    }

    public function day(User $user, int $year, int $day, \DateTimeImmutable $now): array
    {
        $calendar = $this->calendar($user, $year);
        $progress = $this->em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => $day]);
        if (!$progress) throw new CalendarAccessException('ADVENT_DAY_NOT_FOUND', 404, 'Day not found.');
        if ($this->availability->status($progress, $now) === 'locked') {
            throw new CalendarAccessException('ADVENT_DAY_LOCKED', 403, 'Day is locked.');
        }
        $result = $this->em->wrapInTransaction(function () use ($calendar, $day, $now): AdventDayProgress {
            // Lock the calendar first so concurrent first opens have one startedAt.
            $this->em->lock($calendar, LockMode::PESSIMISTIC_WRITE);
            $progress = $this->em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => $day]);
            if (!$progress) throw new \LogicException('Assigned day disappeared.');
            $this->em->lock($progress, LockMode::PESSIMISTIC_WRITE);
            $progress->open($now);
            $calendar->start($now);
            return $progress;
        });
        $task = $result->getTask();
        $imagePath = $task->getImagePath();
        return [
            'day' => $day, 'year' => $year,
            'status' => $this->availability->status($result, $now),
            'availableOn' => $this->availability->availableOn($year, $day),
            'openedAt' => $this->utc($result->getOpenedAt()),
            'completedAt' => $this->utc($result->getCompletedAt()),
            'task' => $task->toUserArray($imagePath === null ? null : $this->images->publicUrl($imagePath)),
        ];
    }

    private function calendar(User $user, int $year): AdventCalendar
    {
        $calendar = $this->em->getRepository(AdventCalendar::class)->findOneBy(['user' => $user, 'year' => $year]);
        if (!$calendar) throw new CalendarAccessException('ADVENT_CALENDAR_NOT_FOUND', 404, 'Calendar not found.');
        return $calendar;
    }

    private function utc(?\DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}

<?php

declare(strict_types=1);

namespace App\Ornament\Service;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Calendar\Service\CalendarAccessException;
use App\Ornament\Entity\UserOrnament;
use App\Ornament\Storage\OrnamentImageStorage;
use App\User\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class UserTreeService
{
    public function __construct(private EntityManagerInterface $em, private OrnamentImageStorage $images) {}

    public function summary(User $user, int $year): array
    {
        $calendar = $this->calendar($user, $year);
        $completedDays = (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(AdventDayProgress::class, 'p')
            ->where('p.calendar = :calendar')
            ->andWhere('p.completedAt IS NOT NULL')
            ->setParameter('calendar', $calendar)
            ->getQuery()->getSingleScalarResult();
        $rewards = $this->em->getRepository(UserOrnament::class)->findBy(['calendar' => $calendar], ['receivedAt' => 'ASC', 'uuid' => 'ASC']);

        return [
            'calendarUuid' => $calendar->getUuid(),
            'year' => $year,
            'completedDays' => $completedDays,
            'totalDays' => 31,
            'completed' => $completedDays === 31,
            'ornaments' => array_map($this->ornament(...), $rewards),
        ];
    }

    public function place(User $user, int $year, string $uuid, int $slot, \DateTimeImmutable $now): array
    {
        if ($slot < 1 || $slot > 31) throw new \InvalidArgumentException('Slot must be between 1 and 31.');
        try {
            return $this->em->wrapInTransaction(function () use ($user, $year, $uuid, $slot, $now): array {
                $calendar = $this->calendar($user, $year);
                // All placements for one calendar share this lock. The DB index remains the final guard.
                $this->em->lock($calendar, LockMode::PESSIMISTIC_WRITE);
                $reward = $this->em->getRepository(UserOrnament::class)->findOneBy(['uuid' => $uuid, 'calendar' => $calendar]);
                if (!$reward) throw new CalendarAccessException('USER_ORNAMENT_NOT_FOUND', 404, 'Ornament not found.');
                if ($reward->getTreeSlot() === $slot) return $this->ornament($reward);
                $occupied = $this->em->getRepository(UserOrnament::class)->findOneBy(['calendar' => $calendar, 'treeSlot' => $slot]);
                if ($occupied) throw new CalendarAccessException('TREE_SLOT_OCCUPIED', 409, 'Tree slot is occupied.');
                $reward->place($slot, $now);
                return $this->ornament($reward);
            });
        } catch (UniqueConstraintViolationException $error) {
            if (str_contains($error->getMessage(), 'uniq_user_ornament_calendar_slot')) {
                throw new CalendarAccessException('TREE_SLOT_OCCUPIED', 409, 'Tree slot is occupied.');
            }
            throw $error;
        }
    }

    private function calendar(User $user, int $year): AdventCalendar
    {
        $calendar = $this->em->getRepository(AdventCalendar::class)->findOneBy(['user' => $user, 'year' => $year]);
        if (!$calendar) throw new CalendarAccessException('ADVENT_CALENDAR_NOT_FOUND', 404, 'Calendar not found.');
        return $calendar;
    }

    private function ornament(UserOrnament $reward): array
    {
        $ornament = $reward->getOrnament();
        $imagePath = $ornament->getImagePath();
        if ($imagePath === null) throw new \LogicException('Awarded ornament has no image.');
        return [
            'uuid' => $reward->getUuid(),
            'name' => $ornament->getName(),
            'imageUrl' => $this->images->publicUrl($imagePath),
            'treeSlot' => $reward->getTreeSlot(),
            'receivedAt' => $this->utc($reward->getReceivedAt()),
            'placedAt' => $this->utc($reward->getPlacedAt()),
            'updatedAt' => $this->utc($reward->getUpdatedAt()),
        ];
    }

    private function utc(?\DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}

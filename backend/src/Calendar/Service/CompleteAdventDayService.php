<?php

declare(strict_types=1);

namespace App\Calendar\Service;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Ornament\Entity\Ornament;
use App\Ornament\Entity\UserOrnament;
use App\Ornament\Storage\OrnamentImageStorage;
use App\User\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class CompleteAdventDayService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CalendarAvailability $availability,
        private OrnamentImageStorage $images,
    ) {}

    public function complete(User $user, int $year, int $day, \DateTimeImmutable $now): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $year, $day, $now): array {
            // Serialise this user's rewards, including completions in different calendars.
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            $calendar = $this->em->getRepository(AdventCalendar::class)->findOneBy(['user' => $user, 'year' => $year]);
            if (!$calendar) throw new CalendarAccessException('ADVENT_CALENDAR_NOT_FOUND', 404, 'Calendar not found.');
            $this->em->lock($calendar, LockMode::PESSIMISTIC_WRITE);
            $progress = $this->em->getRepository(AdventDayProgress::class)->findOneBy(['calendar' => $calendar, 'day' => $day]);
            if (!$progress) throw new CalendarAccessException('ADVENT_DAY_NOT_FOUND', 404, 'Day not found.');
            $this->em->lock($progress, LockMode::PESSIMISTIC_WRITE);

            if ($this->availability->status($progress, $now) === 'locked') {
                throw new CalendarAccessException('ADVENT_DAY_LOCKED', 403, 'Day is locked.');
            }
            if ($progress->getCompletedAt() !== null) {
                $reward = $this->em->getRepository(UserOrnament::class)->findOneBy(['dayProgress' => $progress]);
                if (!$reward) throw new \LogicException('Completed day has no reward.');
                return $this->result($progress, $reward);
            }

            $ornament = $this->chooseOrnament($user);
            if (!$ornament) throw new CalendarAccessException('NO_ACTIVE_ORNAMENTS', 409, 'No active ornaments are available.');
            $progress->complete($now);
            $calendar->start($now);
            $reward = new UserOrnament($progress, $ornament, $now);
            $this->em->persist($reward);
            return $this->result($progress, $reward);
        });
    }

    private function chooseOrnament(User $user): ?Ornament
    {
        $connection = $this->em->getConnection();
        $available = $connection->fetchOne(
            'SELECT o.uuid FROM ornament o WHERE o.is_active = TRUE AND o.image_path IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_ornament uo JOIN advent_calendar ac ON ac.uuid = uo.calendar_id WHERE ac.user_id = :user AND uo.ornament_id = o.uuid) ORDER BY random() LIMIT 1',
            ['user' => $user->getUuid()],
        );
        $uuid = $available ?: $connection->fetchOne('SELECT uuid FROM ornament WHERE is_active = TRUE AND image_path IS NOT NULL ORDER BY random() LIMIT 1');
        return $uuid === false ? null : $this->em->find(Ornament::class, $uuid);
    }

    private function result(AdventDayProgress $progress, UserOrnament $reward): array
    {
        $ornament = $reward->getOrnament();
        $imagePath = $ornament->getImagePath();
        if ($imagePath === null) throw new \LogicException('Awarded ornament has no image.');
        return [
            'completed' => true,
            'completedAt' => $progress->getCompletedAt()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'reward' => [
                'uuid' => $reward->getUuid(),
                'name' => $ornament->getName(),
                'imageUrl' => $this->images->publicUrl($imagePath),
            ],
        ];
    }
}

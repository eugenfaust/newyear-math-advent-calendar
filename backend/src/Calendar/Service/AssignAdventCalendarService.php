<?php

declare(strict_types=1);

namespace App\Calendar\Service;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use App\Template\Entity\AdventTemplate;
use App\Template\Service\TemplateService;
use App\User\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class AssignAdventCalendarService
{
    public function __construct(private EntityManagerInterface $em, private TemplateService $templates) {}

    public function assign(User $user, AdventTemplate $template, int $year): AdventCalendar
    {
        return $this->em->wrapInTransaction(function () use ($user, $template, $year): AdventCalendar {
            // Serializes assignments for one user before checking the unique year.
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            if ($this->em->getRepository(AdventCalendar::class)->findOneBy(['user' => $user, 'year' => $year])) {
                throw new CalendarAssignmentException('CALENDAR_ALREADY_ASSIGNED', 409, 'A calendar is already assigned for this year.');
            }
            // Template edits take a write lock, so all 31 links are read from one version.
            $this->em->lock($template, LockMode::PESSIMISTIC_READ);
            $this->templates->assertAssignable($template);
            $assignments = $this->templates->assignments($template);
            $calendar = new AdventCalendar($user, $template, $year);
            $this->em->persist($calendar);
            foreach ($assignments as $assignment) {
                $this->em->persist(new AdventDayProgress($calendar, $assignment->getDay(), $assignment->getTask()));
            }
            return $calendar;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Calendar\Entity;

use App\Task\Entity\Task;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advent_day_progress')]
#[ORM\UniqueConstraint(name: 'uniq_calendar_day', columns: ['calendar_id', 'day'])]
#[ORM\Index(name: 'idx_calendar_day_task', columns: ['task_id'])]
class AdventDayProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdventCalendar::class)]
    #[ORM\JoinColumn(name: 'calendar_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private AdventCalendar $calendar;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'RESTRICT')]
    private Task $task;

    #[ORM\Column(type: 'smallint')]
    private int $day;

    #[ORM\Column(name: 'opened_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(AdventCalendar $calendar, int $day, Task $task)
    {
        if ($day < 1 || $day > 31) throw new \InvalidArgumentException('Day must be between 1 and 31.');
        $this->calendar = $calendar;
        $this->day = $day;
        $this->task = $task;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getCalendar(): AdventCalendar { return $this->calendar; }
    public function getDay(): int { return $this->day; }
    public function getTask(): Task { return $this->task; }
    public function getOpenedAt(): ?\DateTimeImmutable { return $this->openedAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function open(\DateTimeImmutable $now): void
    {
        if ($this->openedAt === null) $this->openedAt = $this->updatedAt = $now;
    }
}

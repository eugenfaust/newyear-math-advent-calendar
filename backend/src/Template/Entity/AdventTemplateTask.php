<?php

declare(strict_types=1);

namespace App\Template\Entity;

use App\Task\Entity\Task;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advent_template_task')]
#[ORM\UniqueConstraint(name: 'uniq_template_day', columns: ['template_id', 'day'])]
#[ORM\Index(name: 'idx_template_task_task', columns: ['task_id'])]
class AdventTemplateTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdventTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private AdventTemplate $template;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'RESTRICT')]
    private Task $task;

    #[ORM\Column(type: 'smallint')]
    private int $day;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(AdventTemplate $template, int $day, Task $task)
    {
        if ($day < 1 || $day > 31) throw new \InvalidArgumentException('Day must be between 1 and 31.');
        $this->template = $template;
        $this->day = $day;
        $this->task = $task;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getDay(): int { return $this->day; }
    public function getTask(): Task { return $this->task; }
    public function setTask(Task $task): void { $this->task = $task; }
}

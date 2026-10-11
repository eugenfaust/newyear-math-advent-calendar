<?php

declare(strict_types=1);

namespace App\Calendar\Entity;

use App\Template\Entity\AdventTemplate;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advent_calendar')]
#[ORM\UniqueConstraint(name: 'uniq_calendar_user_year', columns: ['user_id', 'year'])]
#[ORM\Index(name: 'idx_calendar_template', columns: ['template_id'])]
class AdventCalendar
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: AdventTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'RESTRICT')]
    private AdventTemplate $template;

    #[ORM\Column(type: 'integer')]
    private int $year;

    #[ORM\Column(name: 'started_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, AdventTemplate $template, int $year)
    {
        if ($year < 1 || $year > 9999) throw new \InvalidArgumentException('Year must be between 1 and 9999.');
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $this->uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $this->user = $user;
        $this->template = $template;
        $this->year = $year;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getUuid(): string { return $this->uuid; }
    public function getUser(): User { return $this->user; }
    public function getTemplate(): AdventTemplate { return $this->template; }
    public function getYear(): int { return $this->year; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function start(): void
    {
        if ($this->startedAt === null) $this->startedAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

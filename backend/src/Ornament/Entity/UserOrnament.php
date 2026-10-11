<?php

declare(strict_types=1);

namespace App\Ornament\Entity;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Entity\AdventDayProgress;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_ornament')]
#[ORM\UniqueConstraint(name: 'uniq_user_ornament_day', columns: ['day_progress_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_ornament_calendar_slot', columns: ['calendar_id', 'tree_slot'], options: ['where' => '(tree_slot IS NOT NULL)'])]
#[ORM\Index(name: 'idx_user_ornament_calendar', columns: ['calendar_id'])]
#[ORM\Index(name: 'idx_user_ornament_ornament', columns: ['ornament_id'])]
class UserOrnament
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: AdventCalendar::class)]
    #[ORM\JoinColumn(name: 'calendar_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private AdventCalendar $calendar;

    #[ORM\OneToOne(targetEntity: AdventDayProgress::class)]
    #[ORM\JoinColumn(name: 'day_progress_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AdventDayProgress $dayProgress;

    #[ORM\ManyToOne(targetEntity: Ornament::class)]
    #[ORM\JoinColumn(name: 'ornament_id', referencedColumnName: 'uuid', nullable: false, onDelete: 'RESTRICT')]
    private Ornament $ornament;

    #[ORM\Column(name: 'tree_slot', type: 'smallint', nullable: true)]
    private ?int $treeSlot = null;

    #[ORM\Column(name: 'received_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(name: 'placed_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $placedAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(AdventDayProgress $dayProgress, Ornament $ornament, \DateTimeImmutable $now)
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $this->uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $this->calendar = $dayProgress->getCalendar();
        $this->dayProgress = $dayProgress;
        $this->ornament = $ornament;
        $this->receivedAt = $this->updatedAt = $now;
    }

    public function getUuid(): string { return $this->uuid; }
    public function getCalendar(): AdventCalendar { return $this->calendar; }
    public function getDayProgress(): AdventDayProgress { return $this->dayProgress; }
    public function getOrnament(): Ornament { return $this->ornament; }
    public function getTreeSlot(): ?int { return $this->treeSlot; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function getPlacedAt(): ?\DateTimeImmutable { return $this->placedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function place(int $slot, \DateTimeImmutable $now): void
    {
        if ($slot < 1 || $slot > 31) throw new \InvalidArgumentException('Slot must be between 1 and 31.');
        if ($this->treeSlot === $slot) return;
        $this->treeSlot = $slot;
        $this->placedAt = $this->updatedAt = $now;
    }
}

<?php

declare(strict_types=1);

namespace App\Ornament\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ornament')]
#[ORM\Index(name: 'idx_ornament_active_created', columns: ['is_active', 'created_at'])]
class Ornament
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(name: 'image_path', length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $this->uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $this->name = $name;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getUuid(): string { return $this->uuid; }
    public function getName(): string { return $this->name; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setName(string $name): void { $this->name = $name; $this->touch(); }
    public function setImagePath(string $path): void { $this->imagePath = $path; $this->touch(); }
    public function setActive(bool $active): void { $this->isActive = $active; $this->touch(); }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
}

<?php

declare(strict_types=1);

namespace App\Task\Entity;

use App\Task\AnswerType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'idx_task_active_created', columns: ['is_active', 'created_at'])]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(name: 'image_path', length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(name: 'answer_type', enumType: AnswerType::class)]
    private AnswerType $answerType = AnswerType::NONE;

    #[ORM\Column(name: 'correct_answer', type: 'text', nullable: true)]
    private ?string $correctAnswer = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $choices = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $solution = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $title, string $content)
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $this->uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getUuid(): string { return $this->uuid; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function getAnswerType(): AnswerType { return $this->answerType; }
    public function getCorrectAnswer(): ?string { return $this->correctAnswer; }
    /** @return list<string>|null */
    public function getChoices(): ?array { return $this->choices; }
    public function getSolution(): ?string { return $this->solution; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setContent(string $content): void { $this->content = $content; $this->touch(); }
    public function setImagePath(?string $path): void { $this->imagePath = $path; $this->touch(); }
    public function setAnswerType(AnswerType $type): void { $this->answerType = $type; $this->touch(); }
    public function setCorrectAnswer(?string $answer): void { $this->correctAnswer = $answer; $this->touch(); }
    /** @param list<string>|null $choices */
    public function setChoices(?array $choices): void { $this->choices = $choices; $this->touch(); }
    public function setSolution(?string $solution): void { $this->solution = $solution; $this->touch(); }
    public function setActive(bool $active): void { $this->isActive = $active; $this->touch(); }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }

    public function toUserArray(?string $imageUrl): array
    {
        $result = ['uuid' => $this->uuid, 'title' => $this->title, 'content' => $this->content,
            'imageUrl' => $imageUrl, 'answerType' => $this->answerType->value];
        if ($this->answerType === AnswerType::MULTIPLE_CHOICE) $result['choices'] = $this->choices;
        return $result;
    }
}

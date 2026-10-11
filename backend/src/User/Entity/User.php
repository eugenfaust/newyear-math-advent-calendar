<?php

declare(strict_types=1);

namespace App\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_login', columns: ['login'])]
#[UniqueEntity(fields: ['login'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_.-]+$/', message: 'Login may contain letters, numbers, _, . and -.')]
    private string $login;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $name;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $login, string $name)
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $this->uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $this->login = $login;
        $this->name = $name;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getUuid(): string { return $this->uuid; }
    public function getUserIdentifier(): string { return $this->login; }
    public function getLogin(): string { return $this->login; }
    public function getName(): string { return $this->name; }
    public function getEmail(): ?string { return $this->email; }
    #[Ignore]
    public function getPassword(): ?string { return $this->passwordHash; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function eraseCredentials(): void {}

    public function setLogin(string $login): void { $this->login = $login; $this->touch(); }
    public function setName(string $name): void { $this->name = $name; $this->touch(); }
    public function setEmail(?string $email): void { $this->email = $email; $this->touch(); }
    public function setPasswordHash(string $hash): void
    {
        if (password_get_info($hash)['algo'] === null) {
            throw new \InvalidArgumentException('Expected a password hash.');
        }
        $this->passwordHash = $hash;
        $this->touch();
    }
    public function setRoles(array $roles): void
    {
        if (array_diff($roles, ['ROLE_USER', 'ROLE_ADMIN'])) {
            throw new \InvalidArgumentException('Unsupported role.');
        }
        $this->roles = array_values(array_unique($roles));
        $this->touch();
    }
    public function setActive(bool $active): void { $this->isActive = $active; $this->touch(); }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }

    public function toPublicArray(): array
    {
        return ['uuid' => $this->uuid, 'login' => $this->login, 'displayName' => $this->name, 'roles' => $this->getRoles()];
    }
}

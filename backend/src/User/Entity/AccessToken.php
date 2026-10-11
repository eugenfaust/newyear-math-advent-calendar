<?php

declare(strict_types=1);

namespace App\User\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
#[ORM\Index(name: 'idx_access_token_user', columns: ['user_uuid'])]
#[ORM\Index(name: 'idx_access_token_expires', columns: ['expires_at'])]
class AccessToken
{
    #[ORM\Id]
    #[ORM\Column(name: 'token_hash', length: 64)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'issued_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'expires_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'revoked_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(string $tokenHash, User $user, \DateTimeImmutable $issuedAt, \DateTimeImmutable $expiresAt)
    {
        $this->tokenHash = $tokenHash;
        $this->user = $user;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
    }

    public function getUser(): User { return $this->user; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function isValid(): bool { return $this->revokedAt === null && $this->expiresAt > new \DateTimeImmutable('now', new \DateTimeZone('UTC')) && $this->user->isActive(); }
    public function revoke(): void { $this->revokedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
}

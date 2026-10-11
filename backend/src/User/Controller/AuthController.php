<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\AccessToken;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AuthController
{
    public function __construct(private EntityManagerInterface $em, private TokenStorageInterface $tokenStorage) {}

    #[Route('/api/v1/auth/login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return $this->error('INVALID_CONTENT_TYPE', 'Expected application/json.', 415);
        }
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('INVALID_JSON', 'Invalid JSON.', 400);
        }
        $login = is_array($data) ? ($data['login'] ?? null) : null;
        $password = is_array($data) ? ($data['password'] ?? null) : null;
        if (!is_string($login) || !is_string($password) || $login === '' || $password === '') {
            return $this->error('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
        }
        $user = $this->em->getRepository(User::class)->findOneBy(['login' => $login]);
        if (!$user || !$user->isActive() || !$hasher->isPasswordValid($user, $password)) {
            return $this->error('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
        }

        $rawToken = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify('+12 hours');
        $this->em->persist(new AccessToken(hash('sha256', $rawToken), $user, $now, $expiresAt));
        $this->em->flush();

        return new JsonResponse(['accessToken' => $rawToken, 'expiresAt' => $expiresAt->format('Y-m-d\TH:i:s\Z'), 'user' => $user->toPublicArray()], 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/v1/auth/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $authorization = (string) $request->headers->get('Authorization');
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/', $authorization, $matches)) {
            return $this->error('UNAUTHENTICATED', 'Authentication required.', 401);
        }
        $raw = $matches[1];
        $token = $this->em->find(AccessToken::class, hash('sha256', $raw));
        $token?->revoke();
        $this->em->flush();
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/v1/auth/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        return new JsonResponse($user instanceof User ? $user->toPublicArray() : null, 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/v1/admin/session', methods: ['GET'])]
    public function adminSession(): JsonResponse
    {
        return $this->me();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status, ['Cache-Control' => 'no-store']);
    }
}

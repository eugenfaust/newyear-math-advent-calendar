<?php

declare(strict_types=1);

namespace App\User\Security;

use App\User\Entity\AccessToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerAuthenticator extends AbstractAuthenticator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function supports(Request $request): ?bool
    {
        if ($request->getPathInfo() === '/api/v1/auth/logout') {
            return false;
        }
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $raw = substr((string) $request->headers->get('Authorization'), 7);
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            throw new AuthenticationException('Invalid token.');
        }
        $token = $this->em->find(AccessToken::class, hash('sha256', $raw));
        if (!$token?->isValid()) {
            throw new AuthenticationException('Invalid token.');
        }

        return new SelfValidatingPassport(new UserBadge($token->getUser()->getUuid(), static fn () => $token->getUser()));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['code' => 'UNAUTHENTICATED', 'message' => 'Authentication required.'], 401, ['Cache-Control' => 'no-store']);
    }
}

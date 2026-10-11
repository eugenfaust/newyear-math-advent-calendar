<?php

declare(strict_types=1);

namespace App\Ornament\Controller;

use App\Calendar\Service\CalendarAccessException;
use App\Calendar\Service\CalendarAvailability;
use App\Ornament\Service\UserTreeService;
use App\User\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/v1/tree')]
final class UserTreeController
{
    public function __construct(
        private UserTreeService $trees,
        private CalendarAvailability $availability,
        private TokenStorageInterface $tokens,
    ) {}

    #[Route('', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $year = $this->year($request);
        if ($year instanceof JsonResponse) return $year;
        try { return $this->response($this->trees->summary($this->user(), $year)); }
        catch (CalendarAccessException $error) { return $this->domainError($error); }
    }

    #[Route('/ornaments/{uuid}/place', methods: ['POST'])]
    public function place(string $uuid, Request $request): JsonResponse
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return $this->validation('uuid', 'Expected a UUID.');
        }
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return $this->response(['code' => 'UNSUPPORTED_MEDIA_TYPE', 'message' => 'Expected application/json.'], 415);
        }
        $year = $this->year($request);
        if ($year instanceof JsonResponse) return $year;
        try { $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return $this->validation('slot', 'Expected a JSON object with slot from 1 to 31.'); }
        if (!$body instanceof \stdClass || !isset($body->slot) || !is_int($body->slot) || $body->slot < 1 || $body->slot > 31) {
            return $this->validation('slot', 'Expected an integer from 1 to 31.');
        }
        try { return $this->response($this->trees->place($this->user(), $year, $uuid, $body->slot, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))); }
        catch (CalendarAccessException $error) { return $this->domainError($error); }
    }

    private function year(Request $request): int|JsonResponse
    {
        $year = $request->query->get('year');
        if ($year === null) return $this->availability->currentYear(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        if (!is_string($year) || !preg_match('/^[1-9][0-9]{0,3}$/', $year)) return $this->validation('year', 'Expected a year from 1 to 9999.');
        return (int) $year;
    }

    private function user(): User
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User) throw new \LogicException('Authenticated user expected.');
        return $user;
    }

    private function validation(string $field, string $message): JsonResponse
    {
        return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => [['field' => $field, 'message' => $message]]], 422);
    }

    private function domainError(CalendarAccessException $error): JsonResponse
    {
        return $this->response(['code' => $error->errorCode, 'message' => $error->getMessage()], $error->httpStatus);
    }

    private function response(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
    }
}

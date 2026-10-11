<?php

declare(strict_types=1);

namespace App\Calendar\Controller;

use App\Calendar\Service\CalendarAccessException;
use App\Calendar\Service\CalendarAvailability;
use App\Calendar\Service\CompleteAdventDayService;
use App\Calendar\Service\UserCalendarService;
use App\User\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/v1/calendar')]
final class UserCalendarController
{
    public function __construct(
        private UserCalendarService $calendars,
        private CalendarAvailability $availability,
        private CompleteAdventDayService $completion,
        private TokenStorageInterface $tokens,
    ) {}

    #[Route('', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $year = $this->year($request, $now);
        if ($year instanceof JsonResponse) return $year;
        try { return $this->response($this->calendars->summary($this->user(), $year, $now)); }
        catch (CalendarAccessException $error) { return $this->error($error); }
    }

    #[Route('/days/{day}', methods: ['GET'])]
    public function day(string $day, Request $request): JsonResponse
    {
        if (!preg_match('/^(?:[1-9]|[12][0-9]|3[01])$/', $day)) return $this->validation('day', 'Expected a day from 1 to 31.');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $year = $this->year($request, $now);
        if ($year instanceof JsonResponse) return $year;
        try { return $this->response($this->calendars->day($this->user(), $year, (int) $day, $now)); }
        catch (CalendarAccessException $error) { return $this->error($error); }
    }

    #[Route('/days/{day}/complete', methods: ['POST'])]
    public function complete(string $day, Request $request): JsonResponse
    {
        if (!preg_match('/^(?:[1-9]|[12][0-9]|3[01])$/', $day)) return $this->validation('day', 'Expected a day from 1 to 31.');
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return $this->response(['code' => 'UNSUPPORTED_MEDIA_TYPE', 'message' => 'Expected application/json.'], 415);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $year = $this->year($request, $now);
        if ($year instanceof JsonResponse) return $year;
        try { return $this->response($this->completion->complete($this->user(), $year, (int) $day, $now)); }
        catch (CalendarAccessException $error) { return $this->error($error); }
    }

    private function user(): User
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User) throw new \LogicException('Authenticated user expected.');
        return $user;
    }

    private function year(Request $request, \DateTimeImmutable $now): int|JsonResponse
    {
        $year = $request->query->get('year');
        if ($year === null) return $this->availability->currentYear($now);
        if (!is_string($year) || !preg_match('/^[1-9][0-9]{0,3}$/', $year)) return $this->validation('year', 'Expected a year from 1 to 9999.');
        return (int) $year;
    }

    private function validation(string $field, string $message): JsonResponse
    {
        return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => [['field' => $field, 'message' => $message]]], 422);
    }

    private function error(CalendarAccessException $error): JsonResponse
    {
        return $this->response(['code' => $error->errorCode, 'message' => $error->getMessage()], $error->httpStatus);
    }

    private function response(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
    }
}

<?php

declare(strict_types=1);

namespace App\Calendar\Controller;

use App\Calendar\Entity\AdventCalendar;
use App\Calendar\Service\AssignAdventCalendarService;
use App\Calendar\Service\CalendarAssignmentException;
use App\Template\Entity\AdventTemplate;
use App\Template\Service\TemplateException;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/users/{uuid}/calendars', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'])]
final class AdminCalendarsController
{
    public function __construct(private EntityManagerInterface $em, private AssignAdventCalendarService $assignment) {}

    #[Route('', methods: ['GET'])]
    public function list(string $uuid, Request $request): JsonResponse
    {
        $user = $this->em->find(User::class, $uuid);
        if (!$user) return $this->error('USER_NOT_FOUND', 'User not found.', 404);
        $page = $request->query->get('page', '1');
        $perPage = $request->query->get('perPage', '20');
        if (!is_string($page) || !preg_match('/^[1-9][0-9]{0,8}$/', $page) || !is_string($perPage) || !preg_match('/^[1-9][0-9]{0,2}$/', $perPage) || (int) $perPage > 100) return $this->validation('pagination', 'Invalid page or perPage.');
        $page = (int) $page;
        $perPage = (int) $perPage;
        $query = $this->em->getRepository(AdventCalendar::class)->createQueryBuilder('c')->where('c.user = :user')->setParameter('user', $user);
        $total = (int) (clone $query)->select('COUNT(c.uuid)')->getQuery()->getSingleScalarResult();
        $calendars = $query->orderBy('c.year', 'DESC')->addOrderBy('c.uuid', 'ASC')->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        return $this->response(['items' => array_map($this->serialize(...), $calendars), 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)]]);
    }

    #[Route('', methods: ['POST'])]
    public function create(string $uuid, Request $request): JsonResponse
    {
        $user = $this->em->find(User::class, $uuid);
        if (!$user) return $this->error('USER_NOT_FOUND', 'User not found.', 404);
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) return $this->error('INVALID_CONTENT_TYPE', 'Expected application/json.', 415);
        try { $decoded = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return $this->error('INVALID_JSON', 'Invalid JSON.', 400); }
        if (!is_object($decoded)) return $this->validation('body', 'Expected an object.');
        $data = (array) $decoded;
        if (array_diff(array_keys($data), ['templateUuid', 'year'])) return $this->validation('body', 'Unknown field.');
        if (!isset($data['templateUuid']) || !is_string($data['templateUuid']) || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $data['templateUuid'])) return $this->validation('templateUuid', 'Expected a template UUID.');
        if (!isset($data['year']) || !is_int($data['year']) || $data['year'] < 1 || $data['year'] > 9999) return $this->validation('year', 'Expected a year from 1 to 9999.');
        $template = $this->em->find(AdventTemplate::class, $data['templateUuid']);
        if (!$template) return $this->error('TEMPLATE_NOT_FOUND', 'Template not found.', 404);
        try { $calendar = $this->assignment->assign($user, $template, $data['year']); }
        catch (CalendarAssignmentException|TemplateException $exception) { return $this->error($exception->errorCode, $exception->getMessage(), $exception->httpStatus); }
        return $this->response($this->serialize($calendar), 201);
    }

    private function serialize(AdventCalendar $calendar): array
    {
        $template = $calendar->getTemplate();
        return ['uuid' => $calendar->getUuid(), 'year' => $calendar->getYear(),
            'template' => ['uuid' => $template->getUuid(), 'name' => $template->getName()],
            'startedAt' => $calendar->getStartedAt()?->format('Y-m-d\TH:i:s\Z'),
            'createdAt' => $calendar->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            'updatedAt' => $calendar->getUpdatedAt()->format('Y-m-d\TH:i:s\Z')];
    }

    private function validation(string $field, string $message): JsonResponse
    {
        return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => [['field' => $field, 'message' => $message]]], 422);
    }
    private function error(string $code, string $message, int $status): JsonResponse { return $this->response(['code' => $code, 'message' => $message], $status); }
    private function response(array $data, int $status = 200): JsonResponse { return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']); }
}

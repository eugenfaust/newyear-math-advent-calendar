<?php

declare(strict_types=1);

namespace App\Template\Controller;

use App\Template\Entity\AdventTemplate;
use App\Template\Service\TemplateException;
use App\Template\Service\TemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/templates')]
final class AdminTemplatesController
{
    private const UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(private EntityManagerInterface $em, private TemplateService $templates) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request->query->get('page', '1'));
        $perPage = $this->positiveInt($request->query->get('perPage', '20'));
        $search = $request->query->get('search', '');
        $active = $request->query->get('isActive');
        if ($page === null || $perPage === null || $perPage > 100 || !is_string($search) || mb_strlen($search) > 150 || !in_array($active, [null, 'true', 'false'], true)) {
            return $this->validation([['field' => 'query', 'message' => 'Invalid list parameters.']]);
        }
        $query = $this->em->getRepository(AdventTemplate::class)->createQueryBuilder('t');
        if (trim($search) !== '') $query->andWhere('LOWER(t.name) LIKE :search')->setParameter('search', '%'.mb_strtolower(addcslashes(trim($search), '%_\\')).'%');
        if ($active !== null) $query->andWhere('t.isActive = :active')->setParameter('active', $active === 'true');
        $total = (int) (clone $query)->select('COUNT(t.uuid)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('t.createdAt', 'DESC')->addOrderBy('t.uuid', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        return $this->response(['items' => array_map(fn (AdventTemplate $template): array => $this->summary($template), $items),
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)]]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, true);
        if ($errors) return $this->validation($errors);
        $template = new AdventTemplate(trim($data['name']));
        $template->setDescription($data['description'] ?? null);
        $template->setYear($data['year'] ?? null);
        $template->setActive($data['isActive'] ?? true);
        $this->em->persist($template);
        $this->em->flush();
        return $this->response($this->detail($template), 201);
    }

    #[Route('/{uuid}', requirements: ['uuid' => self::UUID], methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $template = $this->em->find(AdventTemplate::class, $uuid);
        return $template ? $this->response($this->detail($template)) : $this->notFound();
    }

    #[Route('/{uuid}', requirements: ['uuid' => self::UUID], methods: ['PATCH'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $template = $this->em->find(AdventTemplate::class, $uuid);
        if (!$template) return $this->notFound();
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, false);
        if (!$data) $errors[] = ['field' => 'body', 'message' => 'Provide at least one field.'];
        if ($errors) return $this->validation($errors);
        if (array_key_exists('name', $data)) $template->setName(trim($data['name']));
        if (array_key_exists('description', $data)) $template->setDescription($data['description']);
        if (array_key_exists('year', $data)) $template->setYear($data['year']);
        if (array_key_exists('isActive', $data)) $template->setActive($data['isActive']);
        $this->em->flush();
        return $this->response($this->detail($template));
    }

    #[Route('/{uuid}', requirements: ['uuid' => self::UUID], methods: ['DELETE'])]
    public function deactivate(string $uuid): JsonResponse
    {
        $template = $this->em->find(AdventTemplate::class, $uuid);
        if (!$template) return $this->notFound();
        $template->setActive(false);
        $this->em->flush();
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
    }

    #[Route('/{uuid}/days/{day}', requirements: ['uuid' => self::UUID, 'day' => '\\d+'], methods: ['PUT'])]
    public function setDay(string $uuid, string $day, Request $request): JsonResponse
    {
        if (!preg_match('/^(?:[1-9]|[12][0-9]|3[01])$/', $day)) return $this->validation([['field' => 'day', 'message' => 'Day must be between 1 and 31.']]);
        $template = $this->em->find(AdventTemplate::class, $uuid);
        if (!$template) return $this->notFound();
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        if (array_keys($data) !== ['taskUuid'] || ($data['taskUuid'] !== null && (!$this->validUuid($data['taskUuid'])))) {
            return $this->validation([['field' => 'taskUuid', 'message' => 'Expected a task UUID or null.']]);
        }
        try { $this->templates->setDay($template, (int) $day, $data['taskUuid']); }
        catch (TemplateException $exception) { return $this->error($exception->errorCode, $exception->getMessage(), $exception->httpStatus); }
        return $this->response($this->detail($template));
    }

    #[Route('/{uuid}/validate', requirements: ['uuid' => self::UUID], methods: ['GET'])]
    public function validate(string $uuid): JsonResponse
    {
        $template = $this->em->find(AdventTemplate::class, $uuid);
        if (!$template) return $this->notFound();
        $missing = $this->templates->missingDays($template);
        if ($missing) return $this->response(['code' => 'TEMPLATE_INCOMPLETE', 'message' => 'All 31 days must have tasks.', 'missingDays' => $missing], 422);
        return $this->response(['isComplete' => true, 'missingDays' => []]);
    }

    #[Route('/{uuid}/duplicate', requirements: ['uuid' => self::UUID], methods: ['POST'])]
    public function duplicate(string $uuid, Request $request): JsonResponse
    {
        $source = $this->em->find(AdventTemplate::class, $uuid);
        if (!$source) return $this->notFound();
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        if (array_diff(array_keys($data), ['name']) || (array_key_exists('name', $data) && (!$this->validName($data['name'])))) {
            return $this->validation([['field' => 'name', 'message' => 'Expected a name of 1–150 characters.']]);
        }
        $name = isset($data['name']) ? trim($data['name']) : $source->getName().' (копия)';
        if (!$this->validName($name)) return $this->validation([['field' => 'name', 'message' => 'Name exceeds 150 characters; provide a shorter name.']]);
        $copy = $this->templates->duplicate($source, $name);
        return $this->response($this->detail($copy), 201);
    }

    private function summary(AdventTemplate $template): array
    {
        $filledDays = 31 - count($this->templates->missingDays($template));
        return ['uuid' => $template->getUuid(), 'name' => $template->getName(), 'description' => $template->getDescription(),
            'year' => $template->getYear(), 'isActive' => $template->isActive(), 'filledDays' => $filledDays, 'isComplete' => $filledDays === 31,
            'createdAt' => $template->getCreatedAt()->format('Y-m-d\TH:i:s\Z'), 'updatedAt' => $template->getUpdatedAt()->format('Y-m-d\TH:i:s\Z')];
    }

    private function detail(AdventTemplate $template): array
    {
        $days = array_fill_keys(range(1, 31), null);
        foreach ($this->templates->assignments($template) as $assignment) {
            $task = $assignment->getTask();
            $days[$assignment->getDay()] = ['uuid' => $task->getUuid(), 'title' => $task->getTitle(), 'isActive' => $task->isActive()];
        }
        return [...$this->summaryFromDays($template, $days), 'days' => array_map(
            static fn (int $day, ?array $task): array => ['day' => $day, 'task' => $task], array_keys($days), array_values($days))];
    }

    private function summaryFromDays(AdventTemplate $template, array $days): array
    {
        $filledDays = count(array_filter($days));
        return ['uuid' => $template->getUuid(), 'name' => $template->getName(), 'description' => $template->getDescription(),
            'year' => $template->getYear(), 'isActive' => $template->isActive(), 'filledDays' => $filledDays, 'isComplete' => $filledDays === 31,
            'createdAt' => $template->getCreatedAt()->format('Y-m-d\TH:i:s\Z'), 'updatedAt' => $template->getUpdatedAt()->format('Y-m-d\TH:i:s\Z')];
    }

    private function readJson(Request $request): array|JsonResponse
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) return $this->error('INVALID_CONTENT_TYPE', 'Expected application/json.', 415);
        try { $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return $this->error('INVALID_JSON', 'Invalid JSON.', 400); }
        return is_object($data) ? (array) $data : $this->validation([['field' => 'body', 'message' => 'Expected an object.']]);
    }

    private function checkFields(array $data, bool $create): array
    {
        $errors = [];
        foreach ($data as $field => $value) if (!in_array($field, ['name', 'description', 'year', 'isActive'], true)) $errors[] = ['field' => $field, 'message' => 'Unknown field.'];
        if ($create && !array_key_exists('name', $data)) $errors[] = ['field' => 'name', 'message' => 'Required.'];
        if (array_key_exists('name', $data) && !$this->validName($data['name'])) $errors[] = ['field' => 'name', 'message' => 'Name must be 1–150 characters.'];
        if (array_key_exists('description', $data) && $data['description'] !== null && (!is_string($data['description']) || mb_strlen($data['description']) > 10000)) $errors[] = ['field' => 'description', 'message' => 'Expected text up to 10000 characters or null.'];
        if (array_key_exists('year', $data) && $data['year'] !== null && (!is_int($data['year']) || $data['year'] < 1 || $data['year'] > 9999)) $errors[] = ['field' => 'year', 'message' => 'Expected a year from 1 to 9999 or null.'];
        if (array_key_exists('isActive', $data) && !is_bool($data['isActive'])) $errors[] = ['field' => 'isActive', 'message' => 'Expected a boolean.'];
        return $errors;
    }

    private function validName(mixed $value): bool { return is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= 150; }
    private function validUuid(mixed $value): bool { return is_string($value) && (bool) preg_match('/^'.self::UUID.'$/', $value); }
    private function positiveInt(mixed $value): ?int { return (is_string($value) || is_int($value)) && preg_match('/^[1-9][0-9]*$/', (string) $value) && strlen((string) $value) <= 9 ? (int) $value : null; }
    private function validation(array $details): JsonResponse { return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => $details], 422); }
    private function notFound(): JsonResponse { return $this->error('TEMPLATE_NOT_FOUND', 'Template not found.', 404); }
    private function error(string $code, string $message, int $status): JsonResponse { return $this->response(['code' => $code, 'message' => $message], $status); }
    private function response(array $data, int $status = 200): JsonResponse { return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']); }
}

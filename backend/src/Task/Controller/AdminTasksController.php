<?php

declare(strict_types=1);

namespace App\Task\Controller;

use App\Task\Entity\Task;
use App\Task\Storage\ImageProcessor;
use App\Task\Storage\ImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/tasks')]
final class AdminTasksController
{
    public function __construct(private EntityManagerInterface $em, private ImageStorage $storage, private ImageProcessor $images) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request->query->get('page', '1'));
        $perPage = $this->positiveInt($request->query->get('perPage', '20'));
        $search = $request->query->get('search', '');
        $active = $request->query->get('isActive');
        $answerType = $request->query->get('answerType');
        if ($page === null || $perPage === null || $perPage > 100 || !is_string($search) || mb_strlen($search) > 150
            || !in_array($active, [null, 'true', 'false'], true) || !in_array($answerType, [null, 'NONE', 'NUMBER', 'TEXT', 'MULTIPLE_CHOICE'], true)) {
            return $this->validation([['field' => 'query', 'message' => 'Invalid list parameters.']]);
        }
        $query = $this->em->getRepository(Task::class)->createQueryBuilder('t');
        if (trim($search) !== '') {
            $query->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.content) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(addcslashes(trim($search), '%_\\')).'%');
        }
        if ($active !== null) $query->andWhere('t.isActive = :active')->setParameter('active', $active === 'true');
        if ($answerType !== null) $query->andWhere('t.answerType = :type')->setParameter('type', $answerType);
        $total = (int) (clone $query)->select('COUNT(t.uuid)')->getQuery()->getSingleScalarResult();
        $tasks = $query->orderBy('t.createdAt', 'DESC')->addOrderBy('t.uuid', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        return $this->response([
            'items' => array_map(fn (Task $task) => $this->serialize($task), $tasks),
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ]);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $task = $this->em->find(Task::class, $uuid);
        return $task ? $this->response($this->serialize($task)) : $this->notFound();
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, true);
        if ($errors) return $this->validation($errors);
        $task = new Task(trim($data['title']), $data['content']);
        $task->setSolution($data['solution'] ?? null);
        $task->setCorrectAnswer($data['correctAnswer'] ?? null);
        $task->setActive($data['isActive'] ?? true);
        $this->em->persist($task);
        $this->em->flush();
        return $this->response($this->serialize($task), 201);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['PATCH'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $task = $this->em->find(Task::class, $uuid);
        if (!$task) return $this->notFound();
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, false);
        if (!$data) $errors[] = ['field' => 'body', 'message' => 'Provide at least one field.'];
        if ($errors) return $this->validation($errors);
        if (array_key_exists('title', $data)) $task->setTitle(trim($data['title']));
        if (array_key_exists('content', $data)) $task->setContent($data['content']);
        if (array_key_exists('solution', $data)) $task->setSolution($data['solution']);
        if (array_key_exists('correctAnswer', $data)) $task->setCorrectAnswer($data['correctAnswer']);
        if (array_key_exists('isActive', $data)) $task->setActive($data['isActive']);
        $this->em->flush();
        return $this->response($this->serialize($task));
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['DELETE'])]
    public function deactivate(string $uuid): JsonResponse
    {
        $task = $this->em->find(Task::class, $uuid);
        if (!$task) return $this->notFound();
        $task->setActive(false);
        $this->em->flush();
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
    }

    #[Route('/{uuid}/image', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    public function upload(string $uuid, Request $request): JsonResponse
    {
        $task = $this->em->find(Task::class, $uuid);
        if (!$task) return $this->notFound();
        $file = $request->files->get('image');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || count($request->files->all()) !== 1) {
            return $this->validation([['field' => 'image', 'message' => 'Provide one image file.']]);
        }
        try {
            $path = $this->images->process($file, $this->storage);
        } catch (\InvalidArgumentException $exception) {
            return $this->validation([['field' => 'image', 'message' => $exception->getMessage()]]);
        }
        $oldPath = $task->getImagePath();
        $task->setImagePath($path);
        try {
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->storage->delete($path);
            throw $exception;
        }
        if ($oldPath !== null) $this->storage->delete($oldPath);
        return $this->response($this->serialize($task));
    }

    #[Route('/{uuid}/image', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['DELETE'])]
    public function removeImage(string $uuid): JsonResponse
    {
        $task = $this->em->find(Task::class, $uuid);
        if (!$task) return $this->notFound();
        $oldPath = $task->getImagePath();
        $task->setImagePath(null);
        $this->em->flush();
        if ($oldPath !== null) $this->storage->delete($oldPath);
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
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
        foreach ($data as $field => $value) {
            if (!in_array($field, ['title', 'content', 'answerType', 'correctAnswer', 'solution', 'isActive'], true)) $errors[] = ['field' => $field, 'message' => 'Unknown field.'];
        }
        foreach (['title', 'content'] as $field) {
            if ($create && !array_key_exists($field, $data)) $errors[] = ['field' => $field, 'message' => 'Required.'];
        }
        if (array_key_exists('title', $data) && (!is_string($data['title']) || trim($data['title']) === '' || mb_strlen(trim($data['title'])) > 150)) $errors[] = ['field' => 'title', 'message' => 'Title must be 1–150 characters.'];
        if (array_key_exists('content', $data) && (!is_string($data['content']) || trim($data['content']) === '' || mb_strlen($data['content']) > 50000)) $errors[] = ['field' => 'content', 'message' => 'Content must be 1–50000 characters.'];
        if (array_key_exists('answerType', $data) && $data['answerType'] !== 'NONE') $errors[] = ['field' => 'answerType', 'message' => 'Only NONE is supported in MVP.'];
        if (array_key_exists('correctAnswer', $data) && $data['correctAnswer'] !== null && (!is_string($data['correctAnswer']) || mb_strlen($data['correctAnswer']) > 10000)) $errors[] = ['field' => 'correctAnswer', 'message' => 'Expected text up to 10000 characters or null.'];
        if (array_key_exists('solution', $data) && $data['solution'] !== null && (!is_string($data['solution']) || mb_strlen($data['solution']) > 50000)) $errors[] = ['field' => 'solution', 'message' => 'Expected text up to 50000 characters or null.'];
        if (array_key_exists('isActive', $data) && !is_bool($data['isActive'])) $errors[] = ['field' => 'isActive', 'message' => 'Expected a boolean.'];
        return $errors;
    }

    private function serialize(Task $task): array
    {
        return ['uuid' => $task->getUuid(), 'title' => $task->getTitle(), 'content' => $task->getContent(),
            'imageUrl' => $task->getImagePath() === null ? null : $this->storage->publicUrl($task->getImagePath()),
            'answerType' => $task->getAnswerType()->value, 'correctAnswer' => $task->getCorrectAnswer(),
            'solution' => $task->getSolution(), 'isActive' => $task->isActive(),
            'createdAt' => $task->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            'updatedAt' => $task->getUpdatedAt()->format('Y-m-d\TH:i:s\Z')];
    }

    private function positiveInt(mixed $value): ?int
    {
        return (is_string($value) || is_int($value)) && preg_match('/^[1-9][0-9]*$/', (string) $value) && strlen((string) $value) <= 9 ? (int) $value : null;
    }
    private function validation(array $details): JsonResponse { return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => $details], 422); }
    private function notFound(): JsonResponse { return $this->error('TASK_NOT_FOUND', 'Task not found.', 404); }
    private function error(string $code, string $message, int $status): JsonResponse { return $this->response(['code' => $code, 'message' => $message], $status); }
    private function response(array $data, int $status = 200): JsonResponse { return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']); }
}

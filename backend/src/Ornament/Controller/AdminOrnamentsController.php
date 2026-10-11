<?php

declare(strict_types=1);

namespace App\Ornament\Controller;

use App\Ornament\Entity\Ornament;
use App\Ornament\Storage\OrnamentImageProcessor;
use App\Ornament\Storage\OrnamentImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/ornaments')]
final class AdminOrnamentsController
{
    public function __construct(private EntityManagerInterface $em, private OrnamentImageStorage $storage, private OrnamentImageProcessor $images) {}

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
        $query = $this->em->getRepository(Ornament::class)->createQueryBuilder('o');
        if (trim($search) !== '') {
            $query->andWhere('LOWER(o.name) LIKE :search')->setParameter('search', '%'.mb_strtolower(addcslashes(trim($search), '%_\\')).'%');
        }
        if ($active !== null) $query->andWhere('o.isActive = :active')->setParameter('active', $active === 'true');
        $total = (int) (clone $query)->select('COUNT(o.uuid)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('o.createdAt', 'DESC')->addOrderBy('o.uuid', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        return $this->response(['items' => array_map(fn (Ornament $ornament) => $this->serialize($ornament), $items),
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)]]);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $ornament = $this->em->find(Ornament::class, $uuid);
        return $ornament ? $this->response($this->serialize($ornament)) : $this->notFound();
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, true);
        if ($errors) return $this->validation($errors);
        if (($data['isActive'] ?? false) === true) return $this->validation([['field' => 'isActive', 'message' => 'Upload an image before activation.']]);
        $ornament = new Ornament(trim($data['name']));
        $ornament->setActive(false);
        $this->em->persist($ornament);
        $this->em->flush();
        return $this->response($this->serialize($ornament), 201);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['PATCH'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $ornament = $this->em->find(Ornament::class, $uuid);
        if (!$ornament) return $this->notFound();
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, false);
        if (!$data) $errors[] = ['field' => 'body', 'message' => 'Provide at least one field.'];
        if ($errors) return $this->validation($errors);
        if (array_key_exists('name', $data)) $ornament->setName(trim($data['name']));
        if (($data['isActive'] ?? false) === true && $ornament->getImagePath() === null) return $this->validation([['field' => 'isActive', 'message' => 'Upload an image before activation.']]);
        if (array_key_exists('isActive', $data)) $ornament->setActive($data['isActive']);
        $this->em->flush();
        return $this->response($this->serialize($ornament));
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['DELETE'])]
    public function deactivate(string $uuid): JsonResponse
    {
        $ornament = $this->em->find(Ornament::class, $uuid);
        if (!$ornament) return $this->notFound();
        $ornament->setActive(false);
        $this->em->flush();
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
    }

    #[Route('/{uuid}/image', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    public function upload(string $uuid, Request $request): JsonResponse
    {
        $ornament = $this->em->find(Ornament::class, $uuid);
        if (!$ornament) return $this->notFound();
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || count($request->files->all()) !== 1 || count($request->request->all()) !== 0) {
            return $this->validation([['field' => 'image', 'message' => 'Provide one image file.']]);
        }
        try {
            $path = $this->images->process($file, $this->storage);
        } catch (\InvalidArgumentException $exception) {
            return $this->validation([['field' => 'image', 'message' => $exception->getMessage()]]);
        }
        $ornament->setImagePath($path);
        try {
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->storage->delete($path);
            throw $exception;
        }
        // Keep earlier files: issued rewards may still reference an old URL.
        return $this->response($this->serialize($ornament));
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
            if (!in_array($field, ['name', 'isActive'], true)) $errors[] = ['field' => $field, 'message' => 'Unknown field.'];
        }
        if ($create && !array_key_exists('name', $data)) $errors[] = ['field' => 'name', 'message' => 'Required.'];
        if (array_key_exists('name', $data) && (!is_string($data['name']) || trim($data['name']) === '' || mb_strlen(trim($data['name'])) > 150)) $errors[] = ['field' => 'name', 'message' => 'Name must be 1–150 characters.'];
        if (array_key_exists('isActive', $data) && !is_bool($data['isActive'])) $errors[] = ['field' => 'isActive', 'message' => 'Expected a boolean.'];
        return $errors;
    }

    private function serialize(Ornament $ornament): array
    {
        return ['uuid' => $ornament->getUuid(), 'name' => $ornament->getName(),
            'imageUrl' => $ornament->getImagePath() === null ? null : $this->storage->publicUrl($ornament->getImagePath()),
            'isActive' => $ornament->isActive(),
            'createdAt' => $ornament->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            'updatedAt' => $ornament->getUpdatedAt()->format('Y-m-d\TH:i:s\Z')];
    }

    private function positiveInt(mixed $value): ?int
    {
        return (is_string($value) || is_int($value)) && preg_match('/^[1-9][0-9]*$/', (string) $value) && strlen((string) $value) <= 9 ? (int) $value : null;
    }
    private function validation(array $details): JsonResponse { return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => $details], 422); }
    private function notFound(): JsonResponse { return $this->error('ORNAMENT_NOT_FOUND', 'Ornament not found.', 404); }
    private function error(string $code, string $message, int $status): JsonResponse { return $this->response(['code' => $code, 'message' => $message], $status); }
    private function response(array $data, int $status = 200): JsonResponse { return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']); }
}

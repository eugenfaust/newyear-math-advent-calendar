<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin/users')]
final class AdminUsersController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request->query->get('page', '1'));
        $perPage = $this->positiveInt($request->query->get('perPage', '20'));
        $search = $request->query->get('search', '');
        if ($page === null || $perPage === null || $perPage > 100 || !is_string($search) || mb_strlen($search) > 150) {
            return $this->error('VALIDATION_ERROR', 'Invalid list parameters.', 422);
        }
        $search = trim($search);
        $query = $this->em->getRepository(User::class)->createQueryBuilder('u');
        if ($search !== '') {
            $query->andWhere('LOWER(u.login) LIKE :search OR LOWER(u.name) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%'.strtolower(addcslashes($search, '%_\\')).'%');
        }
        $total = (int) (clone $query)->select('COUNT(u.uuid)')->getQuery()->getSingleScalarResult();
        $users = $query->orderBy('u.createdAt', 'DESC')->addOrderBy('u.uuid', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        return $this->response([
            'items' => array_map(fn (User $user) => $this->serialize($user), $users),
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
        ]);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $user = $this->em->find(User::class, $uuid);
        return $user ? $this->response($this->serialize($user)) : $this->error('USER_NOT_FOUND', 'User not found.', 404);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, true);
        if ($errors) return $this->validation($errors);
        $login = trim($data['login']);
        if ($this->loginExists($login)) return $this->loginConflict();

        $user = new User($login, trim($data['name']));
        $user->setEmail($this->email($data['email'] ?? null));
        $user->setRoles([$data['role']]);
        $user->setActive($data['isActive'] ?? true);
        $user->setTestUser($data['isTestUser'] ?? false);
        $user->setPasswordHash($this->hasher->hashPassword($user, $data['password']));
        $errors = $this->entityErrors($user);
        if ($errors) return $this->validation($errors);
        $this->em->persist($user);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->loginConflict();
        }
        return $this->response($this->serialize($user), 201);
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['PATCH'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $user = $this->em->find(User::class, $uuid);
        if (!$user) return $this->error('USER_NOT_FOUND', 'User not found.', 404);
        $data = $this->readJson($request);
        if ($data instanceof JsonResponse) return $data;
        $errors = $this->checkFields($data, false);
        if (!$data) $errors[] = ['field' => 'body', 'message' => 'Provide at least one field.'];
        if ($errors) return $this->validation($errors);
        if (isset($data['login']) && $this->loginExists(trim($data['login']), $uuid)) return $this->loginConflict();

        if (array_key_exists('login', $data)) $user->setLogin(trim($data['login']));
        if (array_key_exists('name', $data)) $user->setName(trim($data['name']));
        if (array_key_exists('email', $data)) $user->setEmail($this->email($data['email']));
        if (array_key_exists('role', $data)) $user->setRoles([$data['role']]);
        if (array_key_exists('isActive', $data)) $user->setActive($data['isActive']);
        if (array_key_exists('isTestUser', $data)) $user->setTestUser($data['isTestUser']);
        if (array_key_exists('password', $data)) $user->setPasswordHash($this->hasher->hashPassword($user, $data['password']));
        $errors = $this->entityErrors($user);
        if ($errors) return $this->validation($errors);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->loginConflict();
        }
        return $this->response($this->serialize($user));
    }

    #[Route('/{uuid}', requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['DELETE'])]
    public function deactivate(string $uuid): JsonResponse
    {
        $user = $this->em->find(User::class, $uuid);
        if (!$user) return $this->error('USER_NOT_FOUND', 'User not found.', 404);
        $user->setActive(false);
        $this->em->flush();
        return new JsonResponse(null, 204, ['Cache-Control' => 'no-store']);
    }

    private function readJson(Request $request): array|JsonResponse
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return $this->error('INVALID_CONTENT_TYPE', 'Expected application/json.', 415);
        }
        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('INVALID_JSON', 'Invalid JSON.', 400);
        }
        return is_object($data) ? (array) $data : $this->validation([['field' => 'body', 'message' => 'Expected an object.']]);
    }

    private function checkFields(array $data, bool $create): array
    {
        $errors = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['login', 'name', 'email', 'role', 'isActive', 'isTestUser', 'password'], true)) {
                $errors[] = ['field' => $key, 'message' => 'Unknown field.'];
            }
        }
        foreach (['login', 'name', 'role', 'password'] as $field) {
            if ($create && !array_key_exists($field, $data)) $errors[] = ['field' => $field, 'message' => 'Required.'];
        }
        if (array_key_exists('login', $data) && (!is_string($data['login']) || !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', trim($data['login'])))) $errors[] = ['field' => 'login', 'message' => 'Use 1–100 Latin letters, numbers, _, . or -.'];
        if (array_key_exists('name', $data) && (!is_string($data['name']) || trim($data['name']) === '' || mb_strlen(trim($data['name'])) > 150)) $errors[] = ['field' => 'name', 'message' => 'Name must be 1–150 characters.'];
        if (array_key_exists('email', $data) && $data['email'] !== null && (!is_string($data['email']) || ($data['email'] !== '' && (strlen($data['email']) > 180 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))))) $errors[] = ['field' => 'email', 'message' => 'Enter a valid email address.'];
        if (array_key_exists('role', $data) && !in_array($data['role'], ['ROLE_USER', 'ROLE_ADMIN'], true)) $errors[] = ['field' => 'role', 'message' => 'Invalid role.'];
        if (array_key_exists('isActive', $data) && !is_bool($data['isActive'])) $errors[] = ['field' => 'isActive', 'message' => 'Expected a boolean.'];
        if (array_key_exists('isTestUser', $data) && !is_bool($data['isTestUser'])) $errors[] = ['field' => 'isTestUser', 'message' => 'Expected a boolean.'];
        if (array_key_exists('password', $data) && (!is_string($data['password']) || mb_strlen($data['password']) < 6 || mb_strlen($data['password']) > 1024)) $errors[] = ['field' => 'password', 'message' => 'Password must be 6–1024 characters.'];
        return $errors;
    }

    private function entityErrors(User $user): array
    {
        $errors = [];
        foreach ($this->validator->validate($user) as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }
        return $errors;
    }

    private function loginExists(string $login, ?string $exceptUuid = null): bool
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['login' => $login]);
        return $existing !== null && $existing->getUuid() !== $exceptUuid;
    }

    private function email(mixed $email): ?string
    {
        return $email === null || $email === '' ? null : trim($email);
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) return null;
        return preg_match('/^[1-9][0-9]*$/', (string) $value) && strlen((string) $value) <= 9 ? (int) $value : null;
    }

    private function serialize(User $user): array
    {
        return [
            'uuid' => $user->getUuid(), 'login' => $user->getLogin(), 'name' => $user->getName(),
            'email' => $user->getEmail(), 'role' => in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'ROLE_ADMIN' : 'ROLE_USER',
            'isActive' => $user->isActive(), 'isTestUser' => $user->isTestUser(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            'updatedAt' => $user->getUpdatedAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function validation(array $details): JsonResponse
    {
        return $this->response(['code' => 'VALIDATION_ERROR', 'message' => 'Please correct the fields.', 'details' => $details], 422);
    }

    private function loginConflict(): JsonResponse
    {
        return $this->response(['code' => 'LOGIN_TAKEN', 'message' => 'Login is already in use.', 'details' => [['field' => 'login', 'message' => 'Login is already in use.']]], 409);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->response(['code' => $code, 'message' => $message], $status);
    }

    private function response(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
    }
}

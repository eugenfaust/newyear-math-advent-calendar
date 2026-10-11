<?php

namespace App\Shared\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(['code' => 'DATABASE_UNAVAILABLE', 'message' => 'Database is unavailable.'], 503);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}


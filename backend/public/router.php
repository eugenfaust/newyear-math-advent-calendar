<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (is_string($path) && preg_match('~^/uploads/(?:ornaments/[a-f0-9]{32}\.(?:svg|png|webp)|tasks/[a-f0-9]{32}\.(?:png|jpg|webp))$~D', $path)) {
    if (is_file(__DIR__.$path)) {
        return false;
    }

    http_response_code(404);
    exit;
}

require __DIR__.'/index.php';

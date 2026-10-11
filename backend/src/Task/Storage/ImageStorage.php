<?php

declare(strict_types=1);

namespace App\Task\Storage;

interface ImageStorage
{
    public function store(string $sourcePath, string $format): string;
    public function delete(string $path): void;
    public function publicUrl(string $path): string;
}

<?php

declare(strict_types=1);

namespace App\Task\Storage;

final class LocalImageStorage implements ImageStorage
{
    public function __construct(private string $uploadsDir) {}

    public function store(string $sourcePath, string $format): string
    {
        $directory = rtrim($this->uploadsDir, '/').'/tasks';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }
        $name = bin2hex(random_bytes(16)).'.'.$format;
        $destination = $directory.'/'.$name;
        if (!copy($sourcePath, $destination)) {
            throw new \RuntimeException('Cannot store image.');
        }
        chmod($destination, 0644);
        return 'tasks/'.$name;
    }

    public function delete(string $path): void
    {
        if (!preg_match('~^tasks/[a-f0-9]{32}\.(png|jpg|webp)$~D', $path)) return;
        @unlink(rtrim($this->uploadsDir, '/').'/'.$path);
    }

    public function publicUrl(string $path): string
    {
        return '/uploads/'.$path;
    }
}

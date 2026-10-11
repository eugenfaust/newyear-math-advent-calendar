<?php

declare(strict_types=1);

namespace App\Ornament\Storage;

final class LocalOrnamentImageStorage implements OrnamentImageStorage
{
    public function __construct(private string $uploadsDir) {}

    public function store(string $sourcePath, string $extension): string
    {
        $directory = rtrim($this->uploadsDir, '/').'/ornaments';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        if (!copy($sourcePath, $directory.'/'.$name)) throw new \RuntimeException('Cannot store image.');
        chmod($directory.'/'.$name, 0644);
        return 'ornaments/'.$name;
    }

    public function delete(string $path): void
    {
        if (preg_match('~^ornaments/[a-f0-9]{32}\.(svg|png|webp)$~D', $path)) {
            @unlink(rtrim($this->uploadsDir, '/').'/'.$path);
        }
    }

    public function publicUrl(string $path): string { return '/uploads/'.$path; }
}

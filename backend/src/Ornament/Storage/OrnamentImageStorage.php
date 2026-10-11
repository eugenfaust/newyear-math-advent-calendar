<?php

declare(strict_types=1);

namespace App\Ornament\Storage;

interface OrnamentImageStorage
{
    public function store(string $sourcePath, string $extension): string;
    public function delete(string $path): void;
    public function publicUrl(string $path): string;
}

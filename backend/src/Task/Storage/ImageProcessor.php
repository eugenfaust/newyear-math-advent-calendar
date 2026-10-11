<?php

declare(strict_types=1);

namespace App\Task\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageProcessor
{
    public function process(UploadedFile $file, ImageStorage $storage): string
    {
        if (!$file->isValid() || $file->getSize() === false || $file->getSize() > 5 * 1024 * 1024 || $file->getSize() === 0) {
            throw new \InvalidArgumentException('Image must be at most 5 MB.');
        }
        $path = $file->getPathname();
        $info = @getimagesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $formats = ['image/png' => ['png', IMAGETYPE_PNG], 'image/jpeg' => ['jpg', IMAGETYPE_JPEG], 'image/webp' => ['webp', IMAGETYPE_WEBP]];
        if (!$info || !is_string($mime) || !isset($formats[$mime]) || $info[2] !== $formats[$mime][1]
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 8000000) {
            throw new \InvalidArgumentException('Expected a valid PNG, JPEG or WebP image up to 8 megapixels.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) throw new \InvalidArgumentException('Image could not be decoded.');
        $format = $formats[$mime][0];
        $temporary = tempnam(sys_get_temp_dir(), 'task-image-');
        if ($temporary === false) throw new \RuntimeException('Cannot create temporary image.');
        try {
            if ($format === 'jpg') {
                $saved = imagejpeg($image, $temporary, 85);
            } elseif ($format === 'webp') {
                $saved = imagewebp($image, $temporary, 85);
            } else {
                $saved = imagepng($image, $temporary, 6);
            }
            if (!$saved) throw new \RuntimeException('Cannot encode image.');
            return $storage->store($temporary, $format);
        } finally {
            imagedestroy($image);
            @unlink($temporary);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Ornament\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class OrnamentImageProcessor
{
    public function process(UploadedFile $file, OrnamentImageStorage $storage): string
    {
        $size = $file->getSize();
        if (!$file->isValid() || !is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image must be at most 5 MB.');
        }
        $path = $file->getPathname();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            return $this->raster($path, $mime, $storage);
        }
        if (in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml'], true)) {
            return $this->svg($path, $storage);
        }
        throw new \InvalidArgumentException('Expected SVG, PNG or WebP image.');
    }

    private function raster(string $path, string $mime, OrnamentImageStorage $storage): string
    {
        $type = $mime === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_WEBP;
        $extension = $mime === 'image/png' ? 'png' : 'webp';
        $info = @getimagesize($path);
        if (!$info || $info[2] !== $type || $info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 8000000) {
            throw new \InvalidArgumentException('Expected a valid image up to 8 megapixels.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) throw new \InvalidArgumentException('Image could not be decoded.');
        $temporary = tempnam(sys_get_temp_dir(), 'ornament-');
        if ($temporary === false) throw new \RuntimeException('Cannot create temporary image.');
        try {
            $saved = $extension === 'png' ? imagepng($image, $temporary, 6) : imagewebp($image, $temporary, 85);
            if (!$saved) throw new \RuntimeException('Cannot encode image.');
            return $storage->store($temporary, $extension);
        } finally {
            imagedestroy($image);
            @unlink($temporary);
        }
    }

    private function svg(string $path, OrnamentImageStorage $storage): string
    {
        $source = (string) file_get_contents($path);
        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?|<!\[CDATA\[/i', $source)) {
            throw new \InvalidArgumentException('SVG must not contain declarations or external content.');
        }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($source, LIBXML_NONET) || !$document->documentElement) {
                throw new \InvalidArgumentException('Invalid SVG document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->documentElement;
        if ($root->localName !== 'svg' || $root->namespaceURI !== 'http://www.w3.org/2000/svg') {
            throw new \InvalidArgumentException('Expected an SVG image.');
        }
        $nodes = 0;
        $this->checkSvgNode($root, $nodes, 0);
        $viewBox = $root->getAttribute('viewBox');
        if ($viewBox === '' || !preg_match('/^\s*-?\d+(?:\.\d+)?[ ,]+-?\d+(?:\.\d+)?[ ,]+\d+(?:\.\d+)?[ ,]+\d+(?:\.\d+)?\s*$/', $viewBox)) {
            throw new \InvalidArgumentException('SVG requires a numeric viewBox.');
        }
        $bounds = preg_split('/[ ,]+/', trim($viewBox));
        if ($bounds === false || (float) $bounds[2] > 4096 || (float) $bounds[3] > 4096 || (float) $bounds[2] <= 0 || (float) $bounds[3] <= 0) {
            throw new \InvalidArgumentException('SVG viewBox must be at most 4096 by 4096.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'ornament-svg-');
        if ($temporary === false) throw new \RuntimeException('Cannot create temporary image.');
        try {
            if (file_put_contents($temporary, $document->saveXML($root)) === false) throw new \RuntimeException('Cannot save SVG.');
            return $storage->store($temporary, 'svg');
        } finally {
            @unlink($temporary);
        }
    }

    private function checkSvgNode(\DOMElement $element, int &$nodes, int $depth): void
    {
        if (++$nodes > 5000 || $depth > 32) throw new \InvalidArgumentException('SVG is too complex.');
        $elements = ['svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon'];
        $numbers = ['x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height', 'stroke-width', 'opacity', 'fill-opacity', 'stroke-opacity'];
        if ($element->namespaceURI !== 'http://www.w3.org/2000/svg' || !in_array($element->localName, $elements, true)) {
            throw new \InvalidArgumentException('SVG contains unsupported elements.');
        }
        foreach ($element->attributes as $attribute) {
            $name = $attribute->name;
            $value = $attribute->value;
            if ($name === 'xmlns' && $value === 'http://www.w3.org/2000/svg') continue;
            if ($attribute->namespaceURI !== null) throw new \InvalidArgumentException('SVG contains unsupported attributes.');
            if (in_array($name, $numbers, true) && preg_match('/^-?\d+(?:\.\d+)?$/', $value)) continue;
            if ($name === 'viewBox' && $element->localName === 'svg') continue;
            if (in_array($name, ['fill', 'stroke'], true) && preg_match('/^(?:none|white|black|#[0-9a-fA-F]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))$/', $value)) continue;
            if ($name === 'd' && $element->localName === 'path' && preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9eE+.,\s-]+$/', $value)) continue;
            if ($name === 'points' && in_array($element->localName, ['polygon', 'polyline'], true) && preg_match('/^[0-9eE+.,\s-]+$/', $value)) continue;
            if ($name === 'transform' && preg_match('/^(?:(?:translate|scale|rotate|matrix)\([0-9eE+.,\s-]+\)\s*)+$/', $value)) continue;
            if (in_array($name, ['stroke-linecap', 'stroke-linejoin'], true) && in_array($value, ['round', 'square', 'butt', 'miter', 'bevel'], true)) continue;
            throw new \InvalidArgumentException('SVG contains unsupported attributes.');
        }
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) $this->checkSvgNode($child, $nodes, $depth + 1);
            elseif (!$child instanceof \DOMText || trim($child->textContent) !== '') throw new \InvalidArgumentException('SVG contains unsupported content.');
        }
    }
}

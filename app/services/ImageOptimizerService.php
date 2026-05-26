<?php

declare(strict_types=1);

class ImageOptimizerService
{
    public function generateDerivativePaths(string $originalPath): array
    {
        $dir = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);

        return [
            'original' => $originalPath,
            'preview' => $dir . '/' . $filename . '_preview.webp',
            'thumb' => $dir . '/' . $filename . '_thumb.webp',
        ];
    }

    public function optimize(string $originalPath): array
    {
        // Punto de extension para GD/Imagick.
        // Por ahora se conserva el original y se reporta metadata basica.
        $paths = $this->generateDerivativePaths($originalPath);

        return [
            'ok' => is_file($originalPath),
            'paths' => $paths,
        ];
    }
}

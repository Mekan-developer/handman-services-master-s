<?php

namespace App\Support;

use RuntimeException;

class PhotoConverter
{
    private const MAX_HEIGHT = 1800;

    private const WEBP_QUALITY = 85;

    private const CONTENT_MAX_WIDTH = 700;

    private const CONTENT_SIZE_THRESHOLD = 800 * 1024; // 800 KB

    private const CONTENT_WIDTH_THRESHOLD = 800;

    /** Quality ladder tried, in order, when squeezing an image into a byte budget. */
    private const BUDGET_QUALITY_STEPS = [85, 70, 55, 40, 25];

    /** Canvas shrink factor applied when the whole quality ladder overshoots the budget. */
    private const BUDGET_SCALE_FACTOR = 0.75;

    /** Never shrink a budgeted image below this width. */
    private const BUDGET_MIN_WIDTH = 64;

    /**
     * Convert an image file to WebP, scaling down if height > 1080px.
     * Returns the absolute path of the new .webp file.
     */
    public static function convert(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new RuntimeException("Cannot read image: {$absolutePath}");
        }

        $width = $info[0];
        $height = $info[1];
        $mimeType = $info['mime'];

        $image = self::loadImage($absolutePath, $mimeType);

        if ($height > self::MAX_HEIGHT) {
            $image = self::scaleDown($image, $width, $height);
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $absolutePath);

        if (imagewebp($image, $webpPath, self::WEBP_QUALITY) === false) {
            imagedestroy($image);
            throw new RuntimeException("Failed to write WebP: {$webpPath}");
        }

        imagedestroy($image);

        return $webpPath;
    }

    /**
     * Convert a category content image to WebP.
     * Resizes to 700 px wide (height proportional) when file > 800 KB AND width > 800 px.
     */
    public static function convertContent(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new RuntimeException("Cannot read image: {$absolutePath}");
        }

        $width = $info[0];
        $height = $info[1];
        $mimeType = $info['mime'];
        $fileSize = (int) filesize($absolutePath);

        $image = self::loadImage($absolutePath, $mimeType);

        if ($fileSize > self::CONTENT_SIZE_THRESHOLD && $width > self::CONTENT_WIDTH_THRESHOLD) {
            $image = self::scaleToWidth($image, $width, $height, self::CONTENT_MAX_WIDTH);
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $absolutePath);

        if (imagewebp($image, $webpPath, self::WEBP_QUALITY) === false) {
            imagedestroy($image);
            throw new RuntimeException("Failed to write WebP: {$webpPath}");
        }

        imagedestroy($image);

        return $webpPath;
    }

    /**
     * Convert an image to WebP, resizing it to a target width (height stays proportional / auto).
     * Only scales down — images already narrower than the target keep their original size.
     * Returns the absolute path of the new .webp file.
     */
    public static function convertToWidth(string $absolutePath, int $targetWidth): string
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new RuntimeException("Cannot read image: {$absolutePath}");
        }

        $width = $info[0];
        $height = $info[1];
        $mimeType = $info['mime'];

        $image = self::loadImage($absolutePath, $mimeType);

        if ($width > $targetWidth) {
            $image = self::scaleToWidth($image, $width, $height, $targetWidth);
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $absolutePath);

        if (imagewebp($image, $webpPath, self::WEBP_QUALITY) === false) {
            imagedestroy($image);
            throw new RuntimeException("Failed to write WebP: {$webpPath}");
        }

        imagedestroy($image);

        return $webpPath;
    }

    /**
     * Convert an image to WebP that fits into a byte budget, capped at a maximum width.
     * The quality ladder is tried first; when even the lowest quality overshoots the
     * budget the canvas is shrunk and the ladder is retried, down to BUDGET_MIN_WIDTH.
     * Transparency is preserved. Returns the absolute path of the new .webp file.
     */
    public static function convertToMaxBytes(string $absolutePath, int $maxBytes, int $maxWidth): string
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new RuntimeException("Cannot read image: {$absolutePath}");
        }

        $width = $info[0];
        $height = $info[1];
        $mimeType = $info['mime'];

        $image = self::loadImage($absolutePath, $mimeType);

        if ($width > $maxWidth) {
            $image = self::scaleToWidth($image, $width, $height, $maxWidth);
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $absolutePath);

        while (true) {
            foreach (self::BUDGET_QUALITY_STEPS as $quality) {
                if (imagewebp($image, $webpPath, $quality) === false) {
                    imagedestroy($image);
                    throw new RuntimeException("Failed to write WebP: {$webpPath}");
                }

                clearstatcache(true, $webpPath);

                if (filesize($webpPath) <= $maxBytes) {
                    imagedestroy($image);

                    return $webpPath;
                }
            }

            $currentWidth = imagesx($image);
            $nextWidth = (int) round($currentWidth * self::BUDGET_SCALE_FACTOR);

            if ($nextWidth < self::BUDGET_MIN_WIDTH) {
                imagedestroy($image);

                // Budget unreachable — keep the smallest variant we managed to produce.
                return $webpPath;
            }

            $image = self::scaleToWidth($image, $currentWidth, imagesy($image), $nextWidth);
        }
    }

    /** @return \GdImage */
    private static function loadImage(string $path, string $mimeType): mixed
    {
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => throw new RuntimeException("Unsupported image type: {$mimeType}"),
        };

        if ($image === false) {
            throw new RuntimeException("Failed to load image: {$path}");
        }

        // Keep the alpha channel so transparent PNG/WebP/GIF sources don't get
        // flattened onto black when re-encoded.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /** @param \GdImage $image */
    private static function scaleDown(mixed $image, int $width, int $height): mixed
    {
        $ratio = self::MAX_HEIGHT / $height;
        $newWidth = (int) round($width * $ratio);

        $resized = self::createCanvas($newWidth, self::MAX_HEIGHT, $image);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, self::MAX_HEIGHT, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /** @param \GdImage $image */
    private static function scaleToWidth(mixed $image, int $width, int $height, int $newWidth): mixed
    {
        $newHeight = (int) round($height * ($newWidth / $width));

        $resized = self::createCanvas($newWidth, $newHeight, $image);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * Transparent truecolor canvas for a resize. Alpha blending stays off so
     * `imagecopyresampled` copies the source alpha channel verbatim instead of
     * flattening transparent areas onto black.
     *
     * @param  \GdImage  $source  destroyed when the canvas cannot be created
     * @return \GdImage
     */
    private static function createCanvas(int $width, int $height, mixed $source): mixed
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Failed to create resized canvas.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }
}

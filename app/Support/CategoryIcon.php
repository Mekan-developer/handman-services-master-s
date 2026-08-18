<?php

namespace App\Support;

use App\Enums\CategoryIconType;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CategoryIcon
{
    /** Directory on the public disk holding uploaded category images. */
    public const IMAGE_DIRECTORY = 'category-icons';

    /** Uploaded images are squeezed under this size, in bytes. */
    public const IMAGE_MAX_BYTES = 50 * 1024;

    /** Uploaded images are never wider than this, in pixels. */
    public const IMAGE_MAX_WIDTH = 512;

    /**
     * Flat list of allowed preset icon keys (config + legacy uploaded SVGs).
     *
     * @return array<int, string>
     */
    public static function presetKeys(): array
    {
        $configKeys = collect(config('service_icons', []))->flatten()->values()->all();

        return array_merge($configKeys, static::uploadedKeys());
    }

    /**
     * Keys of legacy SVGs uploaded by admins — files named `u-*.svg` in the
     * service_icons disk (public/icons/services). Uploading new SVGs is no
     * longer possible, but the existing ones stay pickable as shared presets.
     *
     * @return array<int, string>
     */
    public static function uploadedKeys(): array
    {
        try {
            return collect(Storage::disk('service_icons')->files())
                ->filter(fn (string $f) => str_starts_with($f, 'u-') && str_ends_with($f, '.svg'))
                ->map(fn (string $f) => basename($f, '.svg'))
                ->values()
                ->all();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Resolve the icon_type / icon columns from validated data and the uploaded
     * file. Uploads are converted to WebP under 50 KB and stored on the public
     * disk; an image replaced or dropped during the same call is removed, since
     * uploaded images belong to a single category.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data, ?UploadedFile $file, ?Category $existing = null): array
    {
        unset($data['icon_file']);

        $type = $data['icon_type'] ?? null;
        $previousImage = $existing?->icon_type === CategoryIconType::Image ? $existing->icon : null;

        if ($type === CategoryIconType::Image->value) {
            if ($file instanceof UploadedFile) {
                $data['icon'] = static::storeImage($file);
            } elseif ($previousImage !== null) {
                $data['icon'] = $previousImage;
            } else {
                [$data['icon_type'], $data['icon']] = [null, null];
            }
        } elseif ($type === CategoryIconType::Custom->value) {
            // Legacy SVG icons are kept as-is; no new SVG uploads are accepted.
            $data['icon'] = $existing?->icon_type === CategoryIconType::Custom ? $existing->icon : null;

            if ($data['icon'] === null) {
                $data['icon_type'] = null;
            }
        } elseif ($type !== CategoryIconType::Preset->value) {
            [$data['icon_type'], $data['icon']] = [null, null];
        }

        if ($previousImage !== null && ($data['icon'] ?? null) !== $previousImage) {
            Storage::disk('public')->delete($previousImage);
        }

        return $data;
    }

    /**
     * Remove a category's uploaded icon file from disk, if any.
     * Preset icons and legacy `u-*` SVGs are shared assets and never purged.
     */
    public static function purge(Category $category): void
    {
        if ($category->icon === null) {
            return;
        }

        // Both new images and the oldest custom icons live on the public disk
        // under a directory (e.g. 'category-icons/uuid.webp'); new-style legacy
        // SVG keys are bare (e.g. 'u-uuid') and shared across categories.
        $isOwnedFile = $category->icon_type === CategoryIconType::Image
            || ($category->icon_type === CategoryIconType::Custom && str_contains($category->icon, '/'));

        if ($isOwnedFile) {
            Storage::disk('public')->delete($category->icon);
        }
    }

    /**
     * Store an uploaded image as a WebP under IMAGE_MAX_BYTES on the public disk.
     * Falls back to the untouched upload when the image can't be converted.
     */
    private static function storeImage(UploadedFile $file): string
    {
        $path = $file->store(static::IMAGE_DIRECTORY, 'public');
        $absolutePath = Storage::disk('public')->path($path);

        try {
            $webpAbsolute = PhotoConverter::convertToMaxBytes(
                $absolutePath,
                static::IMAGE_MAX_BYTES,
                static::IMAGE_MAX_WIDTH,
            );

            if ($webpAbsolute !== $absolutePath) {
                Storage::disk('public')->delete($path);
            }

            return static::relativePath($webpAbsolute);
        } catch (\Throwable $e) {
            // Keep the upload usable, but never fail silently — a missing GD
            // extension would otherwise look like "conversion just doesn't work".
            report($e);

            return $path;
        }
    }

    /** Public-disk-relative path with forward slashes, from an absolute path. */
    private static function relativePath(string $absolutePath): string
    {
        $relative = str_replace(Storage::disk('public')->path(''), '', $absolutePath);

        return ltrim(str_replace('\\', '/', $relative), '/');
    }
}

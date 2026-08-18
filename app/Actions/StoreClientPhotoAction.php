<?php

namespace App\Actions;

use App\Support\PhotoConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The one avatar in the system. A master profile hangs off a client account and
 * reads this same file through the relation, so there is never a second upload
 * to keep in sync.
 *
 * Conversion runs inline: a single portrait is small enough that a queue
 * round-trip would only delay the response and force a status column to poll.
 */
class StoreClientPhotoAction
{
    private const TARGET_WIDTH = 512;

    private const DIRECTORY = 'clients';

    /**
     * Store an uploaded avatar as a width-512 WebP on the public disk. Returns
     * the stored path relative to the disk root. A failed conversion is reported
     * and the untouched original kept, so an upload is never lost to a broken
     * GD build.
     */
    public function handle(UploadedFile $photo): string
    {
        $path = $photo->store(self::DIRECTORY, 'public');
        $absolutePath = Storage::disk('public')->path($path);

        try {
            $webpAbsolute = PhotoConverter::convertToWidth($absolutePath, self::TARGET_WIDTH);

            if ($webpAbsolute !== $absolutePath) {
                Storage::disk('public')->delete($path);
            }

            return ltrim(
                str_replace(Storage::disk('public')->path(''), '', $webpAbsolute),
                DIRECTORY_SEPARATOR
            );
        } catch (\Throwable $e) {
            report($e);

            return $path;
        }
    }
}

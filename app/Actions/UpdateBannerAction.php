<?php

namespace App\Actions;

use App\Models\Banner;
use App\Repositories\BannerRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateBannerAction
{
    public function __construct(private readonly BannerRepository $repository) {}

    /**
     * The old image is removed only after the new one is stored and the row is
     * updated, so a failure at any step never leaves the banner without a picture.
     *
     * @param  array{image: ?UploadedFile, url: ?string, is_active: bool, sort_order: ?int}  $data
     */
    public function handle(Banner $banner, array $data): Banner
    {
        $updateData = [
            'url' => $data['url'] ?? null,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'] ?? $banner->sort_order,
        ];

        $previousImagePath = null;

        if (isset($data['image'])) {
            $previousImagePath = $banner->image_path;
            $updateData['image_path'] = $this->storeImage($data['image']);
        }

        try {
            $banner = $this->repository->update($banner, $updateData);
        } catch (Throwable $exception) {
            if ($previousImagePath !== null) {
                Storage::disk('public')->delete($updateData['image_path']);
            }

            throw $exception;
        }

        if ($previousImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return $banner;
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('banners', 'public');

        if ($path === false) {
            throw new RuntimeException('Failed to store the banner image.');
        }

        return $path;
    }
}

<?php

namespace App\Repositories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BannerRepository
{
    /**
     * Every banner on one list — drag-and-drop reordering cannot span pages,
     * and a carousel never holds more than a handful anyway.
     *
     * @return Collection<int, Banner>
     */
    public function allSorted(): Collection
    {
        return Banner::orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return Collection<int, Banner> */
    public function activeSorted(): Collection
    {
        return Banner::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function findOrFail(int $id): Banner
    {
        return Banner::findOrFail($id);
    }

    public function create(array $data): Banner
    {
        return Banner::create($data);
    }

    public function update(Banner $banner, array $data): Banner
    {
        $banner->update($data);

        return $banner;
    }

    public function delete(Banner $banner): void
    {
        $banner->delete();
    }

    /**
     * @param  array<int, int>  $positions  Map of banner id => sort_order.
     */
    public function reorder(array $positions): void
    {
        DB::transaction(function () use ($positions): void {
            foreach ($positions as $id => $sortOrder) {
                Banner::whereKey($id)->update(['sort_order' => $sortOrder]);
            }
        });
    }
}

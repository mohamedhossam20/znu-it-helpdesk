<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetType;

/**
 * Suggests the next free tag for a device type: PC-0001, PC-0002, …
 * Tags already printed on stickers are kept as-is; this only fills gaps
 * for devices entered without one.
 */
class AssetTagGenerator
{
    public function next(AssetType $type): string
    {
        $prefix = strtoupper($type->code_prefix).'-';

        $highest = Asset::withTrashed()
            ->where('asset_tag', 'like', $prefix.'%')
            ->pluck('asset_tag')
            ->map(fn (string $tag) => substr($tag, strlen($prefix)))
            ->filter(fn (string $suffix) => ctype_digit($suffix))
            ->map(fn (string $suffix) => (int) $suffix)
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}

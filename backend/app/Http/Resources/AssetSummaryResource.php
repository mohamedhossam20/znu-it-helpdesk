<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a requester needs to recognise a device; no serials or specs.
 *
 * @mixin Asset
 */
class AssetSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_tag' => $this->asset_tag,
            'brand' => $this->brand,
            'model' => $this->model,
            'room_id' => $this->room_id,
            'is_mine' => $this->assigned_user_id !== null && $this->assigned_user_id === $request->user()?->id,
            'type' => $this->whenLoaded('type', fn () => [
                'code' => $this->type->code,
                'name_ar' => $this->type->name_ar,
                'name_en' => $this->type->name_en,
                'icon' => $this->type->icon,
            ]),
        ];
    }
}

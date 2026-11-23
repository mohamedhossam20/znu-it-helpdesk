<?php

namespace App\Http\Resources;

use App\Models\Building;
use App\Models\Floor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Building
 */
class BuildingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'floors' => $this->whenLoaded('floors', fn () => $this->floors->map(fn (Floor $floor) => [
                'id' => $floor->id,
                'level' => $floor->level,
                'name_ar' => $floor->name_ar,
                'name_en' => $floor->name_en,
            ])),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket as shown in "my requests" lists.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => $this->status->getLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->priority->getLabel(),
            'source' => $this->source,
            'is_class_in_progress' => $this->is_class_in_progress,
            'location' => $this->location_snapshot,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name_ar' => $this->category->name_ar,
                'name_en' => $this->category->name_en,
                'parent' => $this->category->relationLoaded('parent') && $this->category->parent
                    ? $this->category->parent->only(['id', 'name_ar', 'name_en', 'icon'])
                    : null,
            ]),
            'asset' => new AssetSummaryResource($this->whenLoaded('asset')),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->only(['id', 'name'])),
            'rating' => $this->rating,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
        ];
    }
}

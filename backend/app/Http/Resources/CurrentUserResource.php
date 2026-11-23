<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'extension' => $this->extension,
            'job_title' => $this->job_title,
            'locale' => $this->locale,
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name_ar', 'name_en'])),
            'default_room' => $this->whenLoaded('defaultRoom', fn () => $this->defaultRoom?->locationSnapshot()),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}

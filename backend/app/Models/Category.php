<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name_ar', 'name_en', 'icon', 'default_priority', 'sort_order', 'is_active'])]
class Category extends Model
{
    use HasFactory, HasLocalizedName;

    protected function casts(): array
    {
        return [
            'default_priority' => TicketPriority::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'category_technician');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Technicians are usually linked to the top-level category only.
     */
    public function rootCategory(): self
    {
        return $this->parent_id === null ? $this : $this->loadMissing('parent')->parent->rootCategory();
    }

    public function fullName(): string
    {
        if ($this->parent_id === null) {
            return $this->name;
        }

        return "{$this->loadMissing('parent')->parent->name} › {$this->name}";
    }
}

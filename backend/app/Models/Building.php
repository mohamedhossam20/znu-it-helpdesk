<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name_ar', 'name_en', 'is_active'])]
class Building extends Model
{
    use HasFactory, HasLocalizedName, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class)->orderBy('level');
    }

    public function rooms(): HasManyThrough
    {
        return $this->hasManyThrough(Room::class, Floor::class);
    }
}

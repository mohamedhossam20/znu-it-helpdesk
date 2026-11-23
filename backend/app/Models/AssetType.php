<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'code_prefix', 'name_ar', 'name_en', 'icon'])]
class AssetType extends Model
{
    use HasLocalizedName;

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasLocalizedName
{
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            if (app()->getLocale() === 'en' && filled($this->name_en)) {
                return $this->name_en;
            }

            return $this->name_ar;
        });
    }
}

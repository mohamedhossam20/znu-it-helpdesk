<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'asset_tag', 'asset_type_id', 'room_id', 'assigned_user_id', 'brand', 'model',
    'serial_number', 'specs', 'status', 'purchased_at', 'warranty_until', 'notes',
])]
class Asset extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'status' => AssetStatus::class,
            'purchased_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['room_id', 'assigned_user_id', 'status', 'asset_tag', 'serial_number'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeShared(Builder $query): void
    {
        $query->whereNull('assigned_user_id');
    }

    public function label(): string
    {
        return trim("{$this->asset_tag} — {$this->brand} {$this->model}", ' —');
    }
}

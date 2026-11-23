<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['floor_id', 'code', 'name_ar', 'name_en', 'type', 'is_active'])]
class Room extends Model
{
    use HasFactory, HasLocalizedName, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Room $room): void {
            $room->qr_token ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(User::class, 'default_room_id');
    }

    /**
     * Location names frozen onto a ticket, so moving rooms or people later
     * does not rewrite where past tickets happened.
     *
     * @return array{building: array{id: int, code: string, name_ar: string, name_en: ?string}, floor: array{id: int, level: int, name_ar: string, name_en: ?string}, room: array{id: int, code: string, name_ar: string, name_en: ?string}}
     */
    public function locationSnapshot(): array
    {
        $this->loadMissing('floor.building');
        $floor = $this->floor;
        $building = $floor->building;

        return [
            'building' => $building->only(['id', 'code', 'name_ar', 'name_en']),
            'floor' => $floor->only(['id', 'level', 'name_ar', 'name_en']),
            'room' => $this->only(['id', 'code', 'name_ar', 'name_en']),
        ];
    }

    public function fullLocation(): string
    {
        $this->loadMissing('floor.building');

        return implode(' › ', [$this->floor->building->name, $this->floor->name, $this->name]);
    }
}

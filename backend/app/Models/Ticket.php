<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'number', 'requester_id', 'created_by_id', 'category_id', 'room_id', 'asset_id',
    'location_snapshot', 'source', 'title', 'description', 'priority', 'is_class_in_progress',
    'status', 'assignee_id', 'first_response_at', 'due_at', 'resolved_at', 'closed_at',
    'rating', 'rating_comment',
])]
class Ticket extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const MEDIA_COLLECTION = 'photos';

    protected function casts(): array
    {
        return [
            'location_snapshot' => 'array',
            'source' => TicketSource::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'is_class_in_progress' => 'boolean',
            'first_response_at' => 'datetime',
            'due_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category_id', 'room_id', 'asset_id', 'priority', 'assignee_id', 'title'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic']);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function publicComments(): HasMany
    {
        return $this->comments()->where('is_internal', false);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(TicketStatusChange::class)->oldest('created_at');
    }

    /**
     * Same rule as TicketPolicy::view, as a query for lists.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('tickets.view_all')) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->where('requester_id', $user->id)->orWhere('created_by_id', $user->id);

            if ($user->can('tickets.view_assigned')) {
                $rootIds = $user->technicianCategories()->pluck('categories.id');

                $q->orWhere('assignee_id', $user->id)
                    ->orWhereHas('category', fn (Builder $c) => $c->whereIn('id', $rootIds)->orWhereIn('parent_id', $rootIds));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::openStatuses());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('assignee_id');
    }

    public function snapshotLocationLabel(): string
    {
        $locale = app()->getLocale() === 'en' ? 'name_en' : 'name_ar';

        return collect(['building', 'floor', 'room'])
            ->map(fn (string $part) => $this->location_snapshot[$part][$locale] ?? $this->location_snapshot[$part]['name_ar'] ?? null)
            ->filter()
            ->implode(' › ');
    }
}

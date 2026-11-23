<?php

namespace App\Filament\Resources\Tickets\Actions;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\AttachTicketPhotos;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\UpdateTicketDetails;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Buttons on the ticket page and list. Each one only shows when the
 * matching TicketPolicy ability allows it, and the action re-checks.
 */
class TicketActions
{
    private const UPLOAD_DISK = 'local';

    private const UPLOAD_DIRECTORY = 'tmp/ticket-photos';

    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::take(),
            self::transition('acknowledge', TicketStatus::Acknowledged, __('Acknowledge'), Heroicon::OutlinedHandRaised, 'info'),
            self::transition('start', TicketStatus::InProgress, __('Start work'), Heroicon::OutlinedPlay, 'warning'),
            self::transition('resolve', TicketStatus::Resolved, __('Mark resolved'), Heroicon::OutlinedCheckCircle, 'success', note: 'optional'),
            self::transition('hold', TicketStatus::OnHold, __('Put on hold'), Heroicon::OutlinedPause, 'gray', note: 'required'),
            self::transition('close', TicketStatus::Closed, __('Close'), Heroicon::OutlinedLockClosed, 'gray', note: 'optional'),
            self::transition('reopen', TicketStatus::Reopened, __('Reopen'), Heroicon::OutlinedArrowPath, 'danger', note: 'required'),
            self::transition('cancel', TicketStatus::Cancelled, __('Cancel ticket'), Heroicon::OutlinedXCircle, 'danger', note: 'optional'),
            self::comment(),
            self::assign(),
            self::editDetails(),
        ];
    }

    /**
     * One click for a technician to pick up an unassigned ticket.
     */
    public static function take(): Action
    {
        return Action::make('take')
            ->label(__('Take it'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('primary')
            ->button()
            ->visible(fn (Ticket $record) => $record->assignee_id === null
                && $record->status->canTransitionTo(TicketStatus::Acknowledged)
                && self::user()->can('transition', [$record, TicketStatus::Acknowledged]))
            ->action(fn (Ticket $record, Action $action) => self::run($action, $record, function () use ($record): void {
                app(ChangeTicketStatus::class)->handle($record, TicketStatus::Acknowledged, self::user());
            }, __('The ticket is now yours.')));
    }

    /**
     * @param  'none'|'optional'|'required'  $note
     */
    public static function transition(string $name, TicketStatus $to, string $label, Heroicon $icon, string $color, string $note = 'none'): Action
    {
        $action = Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (Ticket $record) => $record->status->canTransitionTo($to)
                // Picking up an unassigned ticket is the "take" button's job.
                && ! ($record->assignee_id === null && $to === TicketStatus::Acknowledged)
                && self::user()->can('transition', [$record, $to]))
            ->action(fn (Ticket $record, Action $action, array $data) => self::run($action, $record, function () use ($record, $to, $data): void {
                app(ChangeTicketStatus::class)->handle($record, $to, self::user(), $data['note'] ?? null);
            }, __('Status changed to ":status".', ['status' => $to->getLabel()])));

        if ($note === 'none') {
            return $action;
        }

        return $action
            ->modalWidth('lg')
            ->schema([
                Textarea::make('note')
                    ->label($note === 'required' ? __('Reason') : __('Note for the requester (optional)'))
                    ->required($note === 'required')
                    ->rows(3)
                    ->maxLength(1000),
            ]);
    }

    public static function comment(): Action
    {
        return Action::make('comment')
            ->label(__('Add reply'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(fn (Ticket $record) => self::user()->can('comment', $record) || self::user()->can('commentInternal', $record))
            ->modalWidth('2xl')
            ->schema(fn (Ticket $record) => [
                Textarea::make('body')
                    ->label(__('Message'))
                    ->required()
                    ->rows(4)
                    ->maxLength(2000),
                Toggle::make('internal')
                    ->label(__('Internal note (IT team only)'))
                    ->helperText(__('The requester will not see it.'))
                    ->default(! self::user()->can('comment', $record))
                    ->disabled(fn () => ! self::user()->can('comment', $record))
                    ->dehydrated()
                    ->visible(fn () => self::user()->can('commentInternal', $record)),
                self::photoUpload(),
            ])
            ->action(fn (Ticket $record, Action $action, array $data) => self::run($action, $record, function () use ($record, $data): void {
                $comment = app(AddTicketComment::class)->handle($record, self::user(), $data['body'], (bool) ($data['internal'] ?? false));
                app(AttachTicketPhotos::class)->fromDisk($record, $data['photos'] ?? [], self::UPLOAD_DISK, $comment);
            }, __('Reply added.')));
    }

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label(fn (Ticket $record) => $record->assignee_id ? __('Reassign') : __('Assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->visible(fn (Ticket $record) => $record->status->isOpen() && self::user()->can('assign', $record))
            ->fillForm(fn (Ticket $record) => ['assignee_id' => $record->assignee_id])
            ->modalWidth('md')
            ->schema(fn (Ticket $record) => [
                Select::make('assignee_id')
                    ->label(__('Technician'))
                    ->options(fn () => self::eligibleAssignees($record))
                    ->placeholder(__('Unassigned'))
                    ->searchable(),
            ])
            ->action(fn (Ticket $record, Action $action, array $data) => self::run($action, $record, function () use ($record, $data): void {
                app(AssignTicket::class)->handle($record, User::find($data['assignee_id'] ?? null), self::user());
            }, __('Assignment saved.')));
    }

    public static function editDetails(): Action
    {
        return Action::make('editDetails')
            ->label(__('Correct details'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (Ticket $record) => self::user()->can('update', $record))
            ->fillForm(fn (Ticket $record) => [
                'category_id' => $record->category_id,
                'priority' => $record->priority,
                'asset_id' => $record->asset_id,
            ])
            ->modalWidth('lg')
            ->schema(fn (Ticket $record) => [
                Select::make('category_id')
                    ->label(__('Problem'))
                    ->options(self::categoryOptions())
                    ->searchable()
                    ->required(),
                Select::make('priority')
                    ->label(__('Priority'))
                    ->options(TicketPriority::class)
                    ->helperText(__('Changing it recalculates the due time.'))
                    ->required(),
                Select::make('asset_id')
                    ->label(__('Device'))
                    ->options(fn () => self::assetOptions($record->room_id, $record->requester_id))
                    ->placeholder(__('General problem, no specific device'))
                    ->searchable(),
            ])
            ->action(fn (Ticket $record, Action $action, array $data) => self::run($action, $record, function () use ($record, $data): void {
                app(UpdateTicketDetails::class)->handle(
                    $record,
                    self::user(),
                    Category::findOrFail($data['category_id']),
                    $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
                    Asset::find($data['asset_id'] ?? null),
                );
            }, __('Ticket updated.')));
    }

    public static function photoUpload(): FileUpload
    {
        $limits = config('helpdesk.attachments');

        return FileUpload::make('photos')
            ->label(__('Photos'))
            ->image()
            ->multiple()
            ->maxFiles($limits['max_files'])
            ->maxSize($limits['max_kilobytes'])
            ->disk(self::UPLOAD_DISK)
            ->directory(self::UPLOAD_DIRECTORY)
            ->visibility('private');
    }

    /**
     * Leaf problem types grouped under their main type, for selects.
     *
     * @return array<string, array<int, string>>
     */
    public static function categoryOptions(): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Category $root) => [
                $root->name_ar => $root->children->isEmpty()
                    ? [$root->id => $root->name_ar]
                    : $root->children->pluck('name_ar', 'id')->all(),
            ])
            ->all();
    }

    /**
     * Shared devices of the room plus the requester's own devices.
     *
     * @return array<int, string>
     */
    public static function assetOptions(?int $roomId, ?int $requesterId): array
    {
        if (! $roomId && ! $requesterId) {
            return [];
        }

        return Asset::query()
            ->with('type')
            ->where('status', '!=', 'retired')
            ->where(fn ($q) => $q
                ->when($roomId, fn ($r) => $r->orWhere(fn ($s) => $s->where('room_id', $roomId)->whereNull('assigned_user_id')))
                ->when($requesterId, fn ($r) => $r->orWhere('assigned_user_id', $requesterId)))
            ->orderBy('asset_tag')
            ->get()
            ->mapWithKeys(fn (Asset $asset) => [$asset->id => "{$asset->asset_tag} — {$asset->type->name_ar} {$asset->brand} {$asset->model}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function eligibleAssignees(Ticket $ticket): array
    {
        $service = app(TicketAssignmentService::class);

        return User::role(UserRole::adminPanelRoles())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $service->isEligibleAssignee($user, $ticket->category))
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name])
            ->all();
    }

    private static function user(): User
    {
        return auth()->user();
    }

    /**
     * Turn domain errors into a notification instead of a 500. The domain
     * actions work on their own locked copy of the ticket, so the page's
     * instance is reloaded afterwards to show the new state and buttons.
     */
    private static function run(Action $action, Ticket $record, Closure $work, string $success): void
    {
        try {
            $work();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('You are not allowed to do this.'))->send();
            $action->halt();
        }

        $record->refresh();

        Notification::make()->success()->title($success)->send();
    }
}

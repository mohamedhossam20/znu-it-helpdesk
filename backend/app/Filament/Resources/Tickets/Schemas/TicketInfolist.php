<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Ticket;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                Section::make(fn (Ticket $record) => $record->category->fullName())
                                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                                    ->schema([
                                        TextEntry::make('description')
                                            ->hiddenLabel()
                                            ->placeholder(__('No description was written.'))
                                            ->prose(),
                                        ViewEntry::make('photos')
                                            ->hiddenLabel()
                                            ->view('filament.tickets.photos')
                                            ->visible(fn (Ticket $record) => $record->getMedia(Ticket::MEDIA_COLLECTION)
                                                ->contains(fn ($m) => $m->getCustomProperty('comment_id') === null)),
                                    ]),
                                Section::make(__('History'))
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->schema([
                                        ViewEntry::make('timeline')
                                            ->hiddenLabel()
                                            ->view('filament.tickets.timeline'),
                                    ]),
                            ]),
                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('Status'))
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('status')->label(__('Status'))->badge(),
                                        TextEntry::make('priority')->label(__('Priority'))->badge(),
                                        IconEntry::make('is_class_in_progress')
                                            ->label(__('A class is waiting'))
                                            ->boolean()
                                            ->trueIcon(Heroicon::OutlinedAcademicCap)
                                            ->trueColor('danger')
                                            ->visible(fn (Ticket $record) => $record->is_class_in_progress),
                                        TextEntry::make('assignee.name')
                                            ->label(__('Technician'))
                                            ->placeholder(__('Unassigned'))
                                            ->columnSpanFull(),
                                        TextEntry::make('due_at')
                                            ->label(__('Due'))
                                            ->since()
                                            ->dateTimeTooltip()
                                            ->color(fn (Ticket $record) => $record->status->isOpen() && $record->due_at?->isPast() ? 'danger' : null)
                                            ->visible(fn (Ticket $record) => $record->status->isOpen()),
                                        TextEntry::make('created_at')->label(__('Reported'))->since()->dateTimeTooltip(),
                                        TextEntry::make('first_response_at')->label(__('First response'))->since()->dateTimeTooltip()->placeholder('—'),
                                        TextEntry::make('resolved_at')->label(__('Resolved'))->since()->dateTimeTooltip()->placeholder('—'),
                                        TextEntry::make('source')->label(__('Reported via'))->badge()->color('gray'),
                                        TextEntry::make('rating')
                                            ->label(__('Rating'))
                                            ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                                            ->helperText(fn (Ticket $record) => $record->rating_comment)
                                            ->color('warning')
                                            ->visible(fn (Ticket $record) => $record->rating !== null)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make(__('Requested by'))
                                    ->icon(Heroicon::OutlinedUser)
                                    ->schema([
                                        TextEntry::make('requester.name')
                                            ->hiddenLabel()
                                            ->weight('bold')
                                            ->helperText(fn (Ticket $record) => collect([$record->requester->job_title, $record->requester->department?->name_ar])->filter()->implode(' — ')),
                                        TextEntry::make('requester.extension')
                                            ->label(__('Internal extension'))
                                            ->icon(Heroicon::OutlinedPhone)
                                            ->placeholder('—')
                                            ->copyable(),
                                        TextEntry::make('requester.phone')
                                            ->label(__('Mobile'))
                                            ->url(fn (Ticket $record) => $record->requester->phone ? 'tel:'.$record->requester->phone : null)
                                            ->placeholder('—'),
                                        TextEntry::make('createdBy.name')
                                            ->label(__('Logged by'))
                                            ->visible(fn (Ticket $record) => $record->created_by_id !== $record->requester_id),
                                    ]),
                                Section::make(__('Location'))
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->schema([
                                        TextEntry::make('location_snapshot')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn (Ticket $record) => $record->snapshotLocationLabel())
                                            ->weight('bold')
                                            ->url(fn (Ticket $record) => RoomResource::getUrl('edit', ['record' => $record->room_id])),
                                    ]),
                                Section::make(__('Device'))
                                    ->icon(Heroicon::OutlinedComputerDesktop)
                                    ->visible(fn (Ticket $record) => $record->asset_id !== null)
                                    ->schema([
                                        TextEntry::make('asset.asset_tag')
                                            ->hiddenLabel()
                                            ->badge()
                                            ->url(fn (Ticket $record) => AssetResource::getUrl('view', ['record' => $record->asset_id]))
                                            ->helperText(fn (Ticket $record) => trim("{$record->asset->type->name_ar} — {$record->asset->brand} {$record->asset->model}", ' —')),
                                        TextEntry::make('asset_faults')
                                            ->label(__('Earlier faults on this device'))
                                            ->state(fn (Ticket $record) => $record->asset->tickets()->whereKeyNot($record->id)->count())
                                            ->color(fn (int $state) => $state >= 3 ? 'danger' : null)
                                            ->helperText(fn (int $state) => $state >= 3 ? __('Repeated faults: consider replacing the device.') : null),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

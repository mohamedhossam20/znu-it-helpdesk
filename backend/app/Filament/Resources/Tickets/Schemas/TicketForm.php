<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Enums\TicketPriority;
use App\Filament\Forms\LocationFields;
use App\Filament\Resources\Tickets\Actions\TicketActions;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * IT logging a ticket for someone who phoned or walked in. Picking the
 * person fills in their fixed office, exactly like the PWA does.
 */
class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Who has the problem?'))
                    ->columnSpanFull()
                    ->schema([
                        Select::make('requester_id')
                            ->label(__('Requester'))
                            ->options(fn () => [])
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where('is_active', true)
                                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('extension', $search))
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => collect([$u->name, $u->job_title, $u->extension ? __('Ext.').' '.$u->extension : null])->filter()->implode(' — ')]))
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $room = User::with('defaultRoom.floor')->find($state)?->defaultRoom;

                                $set(LocationFields::BUILDING, $room?->floor->building_id);
                                $set(LocationFields::FLOOR, $room?->floor_id);
                                $set('room_id', $room?->id);
                                $set('asset_id', null);
                            }),
                    ]),
                Section::make(__('Where?'))
                    ->description(__('Filled from the person\'s fixed office; change it if the problem is somewhere else.'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ...array_map(fn ($field) => $field->live(), LocationFields::roomPicker()),
                    ]),
                Section::make(__('What is the problem?'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('category_id')
                            ->label(__('Problem'))
                            ->options(fn () => TicketActions::categoryOptions())
                            ->searchable()
                            ->required(),
                        Select::make('asset_id')
                            ->label(__('Device'))
                            ->options(fn (Get $get) => TicketActions::assetOptions($get('room_id'), $get('requester_id')))
                            ->placeholder(__('General problem, no specific device'))
                            ->searchable(),
                        Select::make('priority')
                            ->label(__('Priority'))
                            ->options(TicketPriority::class)
                            ->placeholder(__('Automatic (from problem type)')),
                        Toggle::make('is_class_in_progress')
                            ->label(__('A class is waiting'))
                            ->helperText(__('Makes the ticket urgent.'))
                            ->inline(false),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        TicketActions::photoUpload()->columnSpanFull(),
                    ]),
            ]);
    }
}

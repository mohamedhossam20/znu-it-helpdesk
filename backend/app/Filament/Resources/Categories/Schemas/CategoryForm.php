<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('parent_id')
                            ->label(__('Main type'))
                            ->helperText(__('Leave empty to create a main type (e.g. Audio). Pick one to create a sub-type (e.g. Microphone).'))
                            ->relationship('parent', 'name_ar', fn (Builder $query, ?Category $record) => $query
                                ->whereNull('parent_id')
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
                            ->disabled(fn (?Category $record) => $record?->children()->exists())
                            ->live()
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('name_ar')
                            ->label(__('Name (Arabic)'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('Name (English)'))
                            ->maxLength(255),
                        Select::make('default_priority')
                            ->label(__('Default priority'))
                            ->options(TicketPriority::class)
                            ->placeholder(__('Same as main type')),
                        TextInput::make('icon')
                            ->label(__('Icon'))
                            ->helperText(__('Heroicon name, e.g. heroicon-o-wifi'))
                            ->visible(fn (Get $get) => blank($get('parent_id'))),
                        TextInput::make('sort_order')
                            ->label(__('Order'))
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make(__('Responsible technicians'))
                    ->description(__('Used to route new tickets of this type automatically.'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => blank($get('parent_id')))
                    ->schema([
                        Select::make('technicians')
                            ->hiddenLabel()
                            ->relationship('technicians', 'name', fn (Builder $query) => $query
                                ->role([UserRole::Technician->value, UserRole::ItSupervisor->value])
                                ->where('is_active', true))
                            ->multiple()
                            ->preload(),
                    ]),
            ]);
    }
}

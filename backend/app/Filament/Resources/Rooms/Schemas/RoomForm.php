<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\RoomType;
use App\Filament\Forms\LocationFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Location'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        LocationFields::building()->required(),
                        LocationFields::floor('floor_id'),
                    ]),
                Section::make(__('Room details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Room number / code'))
                            ->helperText(__('As written on the door, e.g. 204 or LAB-3.'))
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                ->where('floor_id', $get('floor_id'))
                                ->withoutTrashed()),
                        Select::make('type')
                            ->label(__('Type'))
                            ->options(RoomType::class)
                            ->default(RoomType::Office)
                            ->required(),
                        TextInput::make('name_ar')
                            ->label(__('Name (Arabic)'))
                            ->placeholder(__('e.g. Student affairs office'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('Name (English)'))
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                    ]),
            ]);
    }
}

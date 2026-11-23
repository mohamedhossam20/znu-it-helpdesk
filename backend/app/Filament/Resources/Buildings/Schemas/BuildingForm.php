<?php

namespace App\Filament\Resources\Buildings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Code'))
                            ->helperText(__('Short code used in imports and QR labels, e.g. MAIN or ENG.'))
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed()),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->inline(false),
                        TextInput::make('name_ar')
                            ->label(__('Name (Arabic)'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('Name (English)'))
                            ->maxLength(255),
                    ]),
            ]);
    }
}

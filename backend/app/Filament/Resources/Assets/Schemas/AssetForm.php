<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetStatus;
use App\Filament\Forms\LocationFields;
use App\Models\AssetType;
use App\Models\User;
use App\Services\AssetTagGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Device'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('asset_type_id')
                            ->label(__('Type'))
                            ->relationship('type', 'name_ar')
                            ->required()
                            ->live()
                            ->preload(),
                        TextInput::make('asset_tag')
                            ->label(__('Asset tag'))
                            ->helperText(__('The code on the sticker. Use the button to suggest the next free one.'))
                            ->required()
                            ->maxLength(30)
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state)))
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed())
                            ->suffixAction(
                                Action::make('suggestTag')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->tooltip(__('Suggest next tag'))
                                    ->disabled(fn (Get $get) => blank($get('asset_type_id')))
                                    ->action(function (Get $get, Set $set): void {
                                        $type = AssetType::find($get('asset_type_id'));

                                        if ($type) {
                                            $set('asset_tag', app(AssetTagGenerator::class)->next($type));
                                        }
                                    }),
                            ),
                        TextInput::make('brand')
                            ->label(__('Brand'))
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label(__('Model'))
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label(__('Serial number'))
                            ->maxLength(255),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(AssetStatus::class)
                            ->default(AssetStatus::InService)
                            ->required(),
                    ]),
                Section::make(__('Location & owner'))
                    ->description(__('Leave "Assigned to" empty for shared devices such as hall projectors or office printers.'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ...LocationFields::roomPicker(),
                        Select::make('assigned_user_id')
                            ->label(__('Assigned to'))
                            ->relationship('assignedUser', 'name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->job_title ? "{$record->name} — {$record->job_title}" : $record->name)
                            ->searchable(['name', 'email'])
                            ->placeholder(__('Shared device'))
                            ->columnSpan(2),
                    ]),
                Section::make(__('More details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        DatePicker::make('purchased_at')
                            ->label(__('Purchase date')),
                        DatePicker::make('warranty_until')
                            ->label(__('Warranty until'))
                            ->afterOrEqual('purchased_at'),
                        KeyValue::make('specs')
                            ->label(__('Specifications'))
                            ->keyLabel(__('Property'))
                            ->valueLabel(__('Value'))
                            ->helperText(__('e.g. os → Windows 11, ram → 16GB, ip → 10.0.2.15'))
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

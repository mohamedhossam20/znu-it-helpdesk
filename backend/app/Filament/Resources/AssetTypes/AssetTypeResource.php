<?php

namespace App\Filament\Resources\AssetTypes;

use App\Filament\NavigationGroup;
use App\Filament\Resources\AssetTypes\Pages\ManageAssetTypes;
use App\Models\AssetType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AssetTypeResource extends Resource
{
    protected static ?string $model = AssetType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return __('Device type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Device types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label(__('Code'))
                    ->helperText(__('Used in the import file, e.g. PC or PROJECTOR.'))
                    ->required()
                    ->maxLength(20)
                    ->alphaDash()
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                    ->unique(ignoreRecord: true),
                TextInput::make('code_prefix')
                    ->label(__('Tag prefix'))
                    ->helperText(__('New tags look like PREFIX-0001.'))
                    ->required()
                    ->maxLength(5)
                    ->alphaNum()
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state)),
                TextInput::make('name_ar')
                    ->label(__('Name (Arabic)'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label(__('Name (English)'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->modifyQueryUsing(fn ($query) => $query->withCount('assets'))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label(__('Code'))->badge()->searchable()->sortable(),
                TextColumn::make('name_ar')->label(__('Name'))->description(fn (AssetType $record) => $record->name_en)->searchable(['name_ar', 'name_en']),
                TextColumn::make('code_prefix')->label(__('Tag prefix')),
                TextColumn::make('assets_count')->label(__('Devices'))->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (AssetType $record) => $record->assets_count > 0)
                    ->tooltip(fn (AssetType $record) => $record->assets_count > 0 ? __('Type is in use by devices') : null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssetTypes::route('/'),
        ];
    }
}

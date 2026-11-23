<?php

namespace App\Filament\Resources\Buildings\RelationManagers;

use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Floor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class FloorsRelationManager extends RelationManager
{
    protected static string $relationship = 'floors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Floors');
    }

    protected static function getModelLabel(): string
    {
        return __('Floor');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('level')
                    ->label(__('Level'))
                    ->helperText(__('0 = ground floor, -1 = basement'))
                    ->integer()
                    ->required()
                    ->minValue(-5)
                    ->maxValue(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule
                        ->where('building_id', $this->getOwnerRecord()->getKey())
                        ->withoutTrashed()),
                TextInput::make('name_ar')
                    ->label(__('Name (Arabic)'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label(__('Name (English)'))
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->modifyQueryUsing(fn ($query) => $query->withCount('rooms'))
            ->defaultSort('level')
            ->columns([
                TextColumn::make('level')
                    ->label(__('Level'))
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label(__('Name'))
                    ->description(fn (Floor $record) => $record->name_en),
                TextColumn::make('rooms_count')
                    ->label(__('Rooms'))
                    ->numeric(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('rooms')
                    ->label(__('Rooms'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (Floor $record) => RoomResource::getUrl('index', [
                        'filters' => ['location' => ['building_id' => $record->building_id, 'floor_id' => $record->id]],
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

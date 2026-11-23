<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['parent', 'technicians'])
                ->withCount(['children', 'tickets'])
                // Main types first, each followed by its sub-types.
                ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, sort_order'))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (Category $record) => $record->parent_id ? '↳ '.$record->name_ar : $record->name_ar)
                    ->weight(fn (Category $record) => $record->parent_id ? null : 'bold')
                    ->description(fn (Category $record) => $record->name_en)
                    ->searchable(['name_ar', 'name_en']),
                TextColumn::make('default_priority')
                    ->label(__('Default priority'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('technicians.name')
                    ->label(__('Technicians'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('tickets_count')
                    ->label(__('Tickets'))
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label(__('Main type'))
                    ->relationship('parent', 'name_ar', fn (Builder $query) => $query->whereNull('parent_id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id) => $q->where(fn (Builder $w) => $w->whereKey($id)->orWhere('parent_id', $id)),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Category $record) => $record->tickets_count > 0 || $record->children_count > 0)
                    ->tooltip(fn (Category $record) => $record->tickets_count > 0 || $record->children_count > 0
                        ? __('Has tickets or sub-types; deactivate it instead')
                        : null),
            ]);
    }
}

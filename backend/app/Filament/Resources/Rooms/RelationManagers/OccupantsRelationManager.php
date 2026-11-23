<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OccupantsRelationManager extends RelationManager
{
    protected static string $relationship = 'occupants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('People based here');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('department'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('job_title')
                    ->label(__('Job title')),
                TextColumn::make('department.name_ar')
                    ->label(__('Department')),
                TextColumn::make('extension')
                    ->label(__('Extension')),
            ])
            ->recordUrl(fn (User $record) => UserResource::canEdit($record) ? UserResource::getUrl('edit', ['record' => $record]) : null);
    }
}

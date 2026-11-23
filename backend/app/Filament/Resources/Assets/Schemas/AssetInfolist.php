<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Device'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('asset_tag')->label(__('Asset tag'))->badge()->size('lg'),
                        TextEntry::make('type.name_ar')->label(__('Type')),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('brand')->label(__('Brand'))->placeholder('—'),
                        TextEntry::make('model')->label(__('Model'))->placeholder('—'),
                        TextEntry::make('serial_number')->label(__('Serial number'))->placeholder('—')->copyable(),
                    ]),
                Section::make(__('Location & owner'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('room_id')
                            ->label(__('Location'))
                            ->formatStateUsing(fn (Asset $record) => $record->room->fullLocation())
                            ->columnSpan(2),
                        TextEntry::make('assignedUser.name')
                            ->label(__('Assigned to'))
                            ->placeholder(__('Shared device'))
                            ->helperText(fn (Asset $record) => $record->assignedUser?->extension ? __('Ext.').' '.$record->assignedUser->extension : null),
                    ]),
                Section::make(__('More details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('purchased_at')->label(__('Purchase date'))->date()->placeholder('—'),
                        TextEntry::make('warranty_until')
                            ->label(__('Warranty until'))
                            ->date()
                            ->placeholder('—')
                            ->color(fn (Asset $record) => $record->warranty_until?->isPast() ? 'danger' : null),
                        KeyValueEntry::make('specs')
                            ->label(__('Specifications'))
                            ->keyLabel(__('Property'))
                            ->valueLabel(__('Value'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}

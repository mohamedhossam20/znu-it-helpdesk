<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Closure;
use App\Filament\Forms\LocationFields;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Account'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Full name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->required(fn (Page $livewire) => $livewire instanceof CreateRecord)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (Page $livewire) => $livewire instanceof CreateRecord ? null : __('Leave empty to keep the current password.')),
                        Select::make('roles')
                            ->label(__('Roles'))
                            ->relationship('roles', 'name', fn (Builder $query) => $query->when(
                                ! auth()->user()?->hasRole(UserRole::SuperAdmin->value),
                                fn (Builder $q) => $q->where('name', '!=', UserRole::SuperAdmin->value),
                            ))
                            ->getOptionLabelFromRecordUsing(fn (Role $record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
                            ->multiple()
                            ->preload()
                            ->required()
                            // Hiding the option is not enough: relationship selects accept any submitted id.
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if (auth()->user()?->hasRole(UserRole::SuperAdmin->value)) {
                                    return;
                                }

                                $superAdminRoleId = Role::where('name', UserRole::SuperAdmin->value)->value('id');

                                if (in_array($superAdminRoleId, array_map('intval', (array) $value), true)) {
                                    $fail(__('Only a super admin can grant the super admin role.'));
                                }
                            })
                            ->default(fn () => Role::where('name', UserRole::Requester->value)->pluck('id')->all()),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->helperText(__('Inactive users cannot log in.'))
                            ->default(true)
                            ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                    ]),
                Section::make(__('Work details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('job_title')
                            ->label(__('Job title'))
                            ->maxLength(255),
                        Select::make('department_id')
                            ->label(__('Department'))
                            ->relationship('department', 'name_ar')
                            ->searchable()
                            ->preload(),
                        TextInput::make('phone')
                            ->label(__('Mobile'))
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('extension')
                            ->label(__('Internal extension'))
                            ->maxLength(10),
                    ]),
                Section::make(__('Fixed location'))
                    ->description(__('For staff who always work from the same office. Their requests will be filled with this place and their devices automatically. Leave empty for people who move between halls.'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema(LocationFields::roomPicker('default_room_id', required: false)),
            ]);
    }
}

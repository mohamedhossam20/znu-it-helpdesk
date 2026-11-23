<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Imports\Concerns\ResolvesRooms;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creates or updates people by email. New accounts get a random password
 * nobody knows; they are activated later (password reset link or the IT
 * team setting one), so an import never spreads usable passwords.
 */
class UsersImporter extends RowImporter
{
    use ResolvesRooms;

    public function headings(): array
    {
        return ['name', 'email', 'phone', 'extension', 'job_title', 'department_name_ar', 'building_code', 'floor_level', 'room_code', 'role'];
    }

    public function exampleRows(): array
    {
        return [
            ['name' => 'أحمد محمد علي', 'email' => 'ahmed.ali@znu.edu.eg', 'phone' => '01000000000', 'extension' => '2145', 'job_title' => 'أخصائي شؤون طلاب', 'department_name_ar' => 'شؤون الطلاب', 'building_code' => 'MAIN', 'floor_level' => 2, 'room_code' => '204', 'role' => 'requester'],
            ['name' => 'د. سارة حسن', 'email' => 'sara.hassan@znu.edu.eg', 'phone' => null, 'extension' => null, 'job_title' => 'مدرس', 'department_name_ar' => 'كلية الهندسة', 'building_code' => null, 'floor_level' => null, 'room_code' => null, 'role' => 'requester'],
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'extension' => ['nullable', 'string', 'max:10'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_name_ar' => ['nullable', 'string', 'max:255'],
            'building_code' => ['nullable', 'required_with:floor_level,room_code', 'string'],
            'floor_level' => ['nullable', 'required_with:building_code,room_code', 'integer'],
            'room_code' => ['nullable', 'required_with:building_code,floor_level', 'string'],
            // Super admins are never created from a spreadsheet.
            'role' => ['nullable', Rule::in([UserRole::Requester->value, UserRole::Technician->value, UserRole::ItSupervisor->value])],
        ];
    }

    protected function importRow(array $row): bool
    {
        $email = Str::lower($row['email']);

        $departmentId = null;
        if (filled($row['department_name_ar'])) {
            $departmentId = Department::firstWhere('name_ar', $row['department_name_ar'])?->id
                ?? throw new RowImportException(__('Department ":name" was not found. Import departments first.', ['name' => $row['department_name_ar']]));
        }

        $roomId = filled($row['room_code'])
            ? $this->findRoom($row['building_code'], $row['floor_level'], $row['room_code'])->id
            : null;

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        if ($user->exists && $user->hasRole(UserRole::SuperAdmin->value)) {
            throw new RowImportException(__('":email" is a super admin account and cannot be changed by import.', ['email' => $email]));
        }

        $user->fill([
            'name' => $row['name'],
            'phone' => $row['phone'],
            'extension' => $row['extension'],
            'job_title' => $row['job_title'],
            'department_id' => $departmentId,
            'default_room_id' => $roomId,
        ]);

        if ($isNew) {
            $user->password = Str::password(32);
        }

        $user->deleted_at = null;
        $user->save();

        if ($isNew || filled($row['role'])) {
            $user->syncRoles([$row['role'] ?? UserRole::Requester->value]);
        }

        return $isNew;
    }
}

<?php

namespace App\Imports;

use App\Models\Department;

class DepartmentsImporter extends RowImporter
{
    public function headings(): array
    {
        return ['name_ar', 'name_en', 'parent_name_ar'];
    }

    public function exampleRows(): array
    {
        return [
            ['name_ar' => 'كلية الهندسة', 'name_en' => 'Faculty of Engineering', 'parent_name_ar' => null],
            ['name_ar' => 'قسم الهندسة المدنية', 'name_en' => 'Civil Engineering', 'parent_name_ar' => 'كلية الهندسة'],
            ['name_ar' => 'شؤون الطلاب', 'name_en' => 'Student Affairs', 'parent_name_ar' => null],
        ];
    }

    protected function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_name_ar' => ['nullable', 'string', 'max:255', 'different:name_ar'],
        ];
    }

    protected function importRow(array $row): bool
    {
        $parentId = null;

        if (filled($row['parent_name_ar'])) {
            $parentId = Department::firstWhere('name_ar', $row['parent_name_ar'])?->id
                ?? throw new RowImportException(__('Parent department ":name" was not found. List it in an earlier row.', ['name' => $row['parent_name_ar']]));
        }

        $department = Department::withTrashed()->firstOrNew(['name_ar' => $row['name_ar']]);
        $isNew = ! $department->exists;

        $department->fill(['name_en' => $row['name_en'] ?? $department->name_en, 'parent_id' => $parentId]);
        $department->deleted_at = null;
        $department->save();

        return $isNew;
    }
}

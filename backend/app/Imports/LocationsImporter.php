<?php

namespace App\Imports;

use App\Enums\RoomType;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Validation\Rule;

/**
 * One row per room; the building and floor are created on first sight.
 */
class LocationsImporter extends RowImporter
{
    public function headings(): array
    {
        return ['building_code', 'building_name_ar', 'building_name_en', 'floor_level', 'floor_name_ar', 'room_code', 'room_name_ar', 'room_name_en', 'room_type'];
    }

    public function exampleRows(): array
    {
        return [
            ['building_code' => 'MAIN', 'building_name_ar' => 'المبنى الرئيسي', 'building_name_en' => 'Main Building', 'floor_level' => 2, 'floor_name_ar' => 'الدور الثاني', 'room_code' => '204', 'room_name_ar' => 'مكتب شؤون الطلاب', 'room_name_en' => 'Student affairs office', 'room_type' => 'office'],
            ['building_code' => 'ENG', 'building_name_ar' => 'مبنى كلية الهندسة', 'building_name_en' => 'Faculty of Engineering', 'floor_level' => 1, 'floor_name_ar' => 'الدور الأول', 'room_code' => '105', 'room_name_ar' => 'قاعة 105', 'room_name_en' => 'Hall 105', 'room_type' => 'lecture_hall'],
        ];
    }

    protected function rules(): array
    {
        return [
            'building_code' => ['required', 'string', 'max:20', 'alpha_dash'],
            'building_name_ar' => ['nullable', 'string', 'max:255'],
            'building_name_en' => ['nullable', 'string', 'max:255'],
            'floor_level' => ['required', 'integer', 'between:-5,50'],
            'floor_name_ar' => ['nullable', 'string', 'max:255'],
            'room_code' => ['required', 'string', 'max:30'],
            'room_name_ar' => ['required', 'string', 'max:255'],
            'room_name_en' => ['nullable', 'string', 'max:255'],
            'room_type' => ['nullable', Rule::enum(RoomType::class)],
        ];
    }

    protected function importRow(array $row): bool
    {
        $code = strtoupper($row['building_code']);
        $building = Building::withTrashed()->firstWhere('code', $code);

        if ($building?->trashed()) {
            $building->restore();
        }

        if (! $building) {
            if (blank($row['building_name_ar'])) {
                throw new RowImportException(__('Building :code is new, so building_name_ar is required.', ['code' => $code]));
            }

            $building = Building::create(['code' => $code, 'name_ar' => $row['building_name_ar'], 'name_en' => $row['building_name_en']]);
        } elseif (filled($row['building_name_ar'])) {
            $building->update(array_filter(['name_ar' => $row['building_name_ar'], 'name_en' => $row['building_name_en']]));
        }

        $level = (int) $row['floor_level'];
        $floor = $building->floors()->firstOrCreate(
            ['level' => $level],
            ['name_ar' => $row['floor_name_ar'] ?? $this->defaultFloorName($level)],
        );

        if (filled($row['floor_name_ar']) && $floor->name_ar !== $row['floor_name_ar']) {
            $floor->update(['name_ar' => $row['floor_name_ar']]);
        }

        $room = Room::withTrashed()->firstOrNew(['floor_id' => $floor->id, 'code' => $row['room_code']]);
        $isNew = ! $room->exists;

        $room->fill([
            'name_ar' => $row['room_name_ar'],
            'name_en' => $row['room_name_en'],
            'type' => $row['room_type'] ?? ($room->type ?? RoomType::Office),
        ]);
        $room->deleted_at = null;
        $room->save();

        return $isNew;
    }

    private function defaultFloorName(int $level): string
    {
        return match (true) {
            $level === 0 => 'الدور الأرضي',
            $level < 0 => 'البدروم '.abs($level),
            default => 'الدور '.$level,
        };
    }
}

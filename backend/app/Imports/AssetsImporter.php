<?php

namespace App\Imports;

use App\Enums\AssetStatus;
use App\Imports\Concerns\ResolvesRooms;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\User;
use App\Services\AssetTagGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssetsImporter extends RowImporter
{
    use ResolvesRooms;

    public function __construct(private readonly AssetTagGenerator $tags) {}

    public function headings(): array
    {
        return ['asset_tag', 'type_code', 'brand', 'model', 'serial_number', 'building_code', 'floor_level', 'room_code', 'assigned_user_email', 'status'];
    }

    public function exampleRows(): array
    {
        return [
            ['asset_tag' => 'PC-0123', 'type_code' => 'PC', 'brand' => 'Dell', 'model' => 'OptiPlex 7090', 'serial_number' => 'ABC123', 'building_code' => 'MAIN', 'floor_level' => 2, 'room_code' => '204', 'assigned_user_email' => 'ahmed.ali@znu.edu.eg', 'status' => 'in_service'],
            ['asset_tag' => null, 'type_code' => 'PROJECTOR', 'brand' => 'Epson', 'model' => 'EB-X51', 'serial_number' => null, 'building_code' => 'ENG', 'floor_level' => 1, 'room_code' => '105', 'assigned_user_email' => null, 'status' => null],
        ];
    }

    protected function rules(): array
    {
        return [
            'asset_tag' => ['nullable', 'string', 'max:30'],
            'type_code' => ['required', 'string', Rule::exists('asset_types', 'code')],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'building_code' => ['required', 'string'],
            'floor_level' => ['required', 'integer'],
            'room_code' => ['required', 'string'],
            'assigned_user_email' => ['nullable', 'email'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
        ];
    }

    protected function normalize(array $row): array
    {
        $row = parent::normalize($row);
        $row['type_code'] = isset($row['type_code']) ? strtoupper($row['type_code']) : null;

        return $row;
    }

    protected function importRow(array $row): bool
    {
        $type = AssetType::firstWhere('code', $row['type_code']);
        $room = $this->findRoom($row['building_code'], $row['floor_level'], $row['room_code']);

        $ownerId = null;
        if (filled($row['assigned_user_email'])) {
            $ownerId = User::firstWhere('email', Str::lower($row['assigned_user_email']))?->id
                ?? throw new RowImportException(__('No user with email ":email". Import users first.', ['email' => $row['assigned_user_email']]));
        }

        $tag = filled($row['asset_tag']) ? strtoupper($row['asset_tag']) : $this->tags->next($type);

        $asset = Asset::withTrashed()->firstOrNew(['asset_tag' => $tag]);
        $isNew = ! $asset->exists;

        $asset->fill([
            'asset_type_id' => $type->id,
            'room_id' => $room->id,
            'assigned_user_id' => $ownerId,
            'brand' => $row['brand'] ?? $asset->brand,
            'model' => $row['model'] ?? $asset->model,
            'serial_number' => $row['serial_number'] ?? $asset->serial_number,
            'status' => $row['status'] ?? ($asset->status ?? AssetStatus::InService),
        ]);
        $asset->deleted_at = null;
        $asset->save();

        return $isNew;
    }
}

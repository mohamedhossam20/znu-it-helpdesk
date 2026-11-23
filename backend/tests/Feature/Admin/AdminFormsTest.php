<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomType;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminFormsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::where('email', 'supervisor@znu.test')->firstOrFail());
    }

    public function test_assets_can_be_filtered_by_personal_or_shared(): void
    {
        $personal = Asset::whereNotNull('assigned_user_id')->get();
        $shared = Asset::whereNull('assigned_user_id')->get();

        Livewire::test(ListAssets::class)
            ->filterTable('assigned', true)
            ->assertCanSeeTableRecords($personal)
            ->assertCanNotSeeTableRecords($shared)
            ->filterTable('assigned', false)
            ->assertCanSeeTableRecords($shared)
            ->assertCanNotSeeTableRecords($personal);
    }

    public function test_room_is_created_on_the_selected_floor_with_a_qr_token(): void
    {
        $building = Building::firstWhere('code', 'ENG');
        $floor = $building->floors()->firstOrFail();

        Livewire::test(CreateRoom::class)
            ->fillForm([
                'location_building_id' => $building->id,
                'floor_id' => $floor->id,
                'code' => '106',
                'type' => RoomType::LectureHall->value,
                'name_ar' => 'قاعة 106',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $room = Room::where('floor_id', $floor->id)->where('code', '106')->firstOrFail();
        $this->assertSame(RoomType::LectureHall, $room->type);
        $this->assertSame(26, strlen($room->qr_token));
    }

    public function test_room_code_must_be_unique_per_floor(): void
    {
        $existing = Room::where('code', '105')->with('floor')->firstOrFail();

        Livewire::test(CreateRoom::class)
            ->fillForm([
                'location_building_id' => $existing->floor->building_id,
                'floor_id' => $existing->floor_id,
                'code' => '105',
                'type' => RoomType::LectureHall->value,
                'name_ar' => 'مكرر',
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_asset_create_form_is_prefilled_from_room_link(): void
    {
        $room = Room::where('code', '105')->with('floor')->firstOrFail();

        Livewire::withQueryParams(['room' => $room->id])
            ->test(CreateAsset::class)
            ->assertSchemaStateSet([
                'room_id' => $room->id,
                'location_floor_id' => $room->floor_id,
                'location_building_id' => $room->floor->building_id,
            ]);
    }

    public function test_asset_is_created_and_tag_is_uppercased(): void
    {
        $room = Room::where('code', '204')->with('floor')->firstOrFail();
        $owner = User::where('email', 'staff@znu.test')->firstOrFail();

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'asset_type_id' => AssetType::firstWhere('code', 'LAPTOP')->id,
                'asset_tag' => 'lt-0001',
                'location_building_id' => $room->floor->building_id,
                'location_floor_id' => $room->floor_id,
                'room_id' => $room->id,
                'assigned_user_id' => $owner->id,
                'brand' => 'Lenovo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('assets', ['asset_tag' => 'LT-0001', 'room_id' => $room->id, 'assigned_user_id' => $owner->id]);
    }

    public function test_user_is_created_with_fixed_location_and_role(): void
    {
        $room = Room::where('code', '210')->with('floor')->firstOrFail();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'موظف جديد',
                'email' => 'new.staff@znu.test',
                'password' => 'S3cure-Passw0rd!',
                'roles' => [Role::findByName('requester')->id],
                'location_building_id' => $room->floor->building_id,
                'location_floor_id' => $room->floor_id,
                'default_room_id' => $room->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new.staff@znu.test')->firstOrFail();
        $this->assertSame($room->id, $user->default_room_id);
        $this->assertTrue($user->hasRole('requester'));
    }

    public function test_supervisor_cannot_grant_super_admin(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'محاولة',
                'email' => 'escalate@znu.test',
                'password' => 'S3cure-Passw0rd!',
                'roles' => [Role::findByName('super_admin')->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertDatabaseMissing('users', ['email' => 'escalate@znu.test']);
    }
}

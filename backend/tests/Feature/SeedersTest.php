<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_seeders_can_run_twice_without_duplicates(): void
    {
        $counts = fn () => [User::count(), Room::count(), Asset::count(), Category::count(), Role::count()];
        $before = $counts();

        $this->seed();

        $this->assertSame($before, $counts());
    }

    public function test_every_room_gets_a_unique_qr_token(): void
    {
        $tokens = Room::pluck('qr_token');

        $this->assertTrue($tokens->every(fn ($token) => strlen($token) === 26));
        $this->assertSame($tokens->count(), $tokens->unique()->count());
    }

    public function test_roles_have_expected_permissions(): void
    {
        $requester = Role::findByName('requester');
        $supervisor = Role::findByName('it_supervisor');

        $this->assertTrue($requester->hasPermissionTo('tickets.create'));
        $this->assertFalse($requester->hasPermissionTo('tickets.view_all'));
        $this->assertTrue($supervisor->hasPermissionTo('tickets.assign'));
        $this->assertFalse($supervisor->hasPermissionTo('settings.manage'));
    }

    public function test_fixed_location_user_has_room_and_devices(): void
    {
        $staff = User::where('email', 'staff@znu.test')->firstOrFail();
        $doctor = User::where('email', 'doctor@znu.test')->firstOrFail();

        $this->assertTrue($staff->hasFixedLocation());
        $this->assertSame('204', $staff->defaultRoom->code);
        $this->assertEqualsCanonicalizing(['PC-0123', 'TEL-2145'], $staff->assets->pluck('asset_tag')->all());
        $this->assertFalse($doctor->hasFixedLocation());
    }

    public function test_room_location_snapshot_contains_building_floor_and_room(): void
    {
        $hall = Room::where('code', '105')->firstOrFail();

        $snapshot = $hall->locationSnapshot();

        $this->assertSame('ENG', $snapshot['building']['code']);
        $this->assertSame(1, $snapshot['floor']['level']);
        $this->assertSame('قاعة 105', $snapshot['room']['name_ar']);
    }
}

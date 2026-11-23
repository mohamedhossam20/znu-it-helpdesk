<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function userByEmail(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function indexPages(): array
    {
        return array_map(fn (string $path) => [$path], [
            'buildings' => '/admin/buildings',
            'rooms' => '/admin/rooms',
            'assets' => '/admin/assets',
            'asset types' => '/admin/asset-types',
            'categories' => '/admin/categories',
            'departments' => '/admin/departments',
            'users' => '/admin/users',
            'import' => '/admin/import-data',
            'create building' => '/admin/buildings/create',
            'create room' => '/admin/rooms/create',
            'create asset' => '/admin/assets/create',
            'create category' => '/admin/categories/create',
            'create user' => '/admin/users/create',
        ]);
    }

    #[DataProvider('indexPages')]
    public function test_super_admin_can_open_every_page(string $path): void
    {
        $this->actingAs($this->userByEmail('admin@znu.test'))->get($path)->assertOk();
    }

    public function test_record_pages_render_with_seeded_data(): void
    {
        $this->actingAs($this->userByEmail('admin@znu.test'));

        $asset = Asset::where('asset_tag', 'PC-0123')->firstOrFail();
        $room = Room::where('code', '204')->firstOrFail();

        $this->get('/admin/buildings/'.Building::firstWhere('code', 'MAIN')->id.'/edit')->assertOk();
        $this->get("/admin/rooms/{$room->id}/edit")->assertOk()->assertSee('مكتب شؤون الطلاب');
        $this->get("/admin/assets/{$asset->id}")->assertOk()->assertSee('OptiPlex 7090');
        $this->get("/admin/assets/{$asset->id}/edit")->assertOk();
        $this->get('/admin/categories/'.Category::whereNull('parent_id')->first()->id.'/edit')->assertOk();
        $this->get('/admin/users/'.$this->userByEmail('staff@znu.test')->id.'/edit')->assertOk();
    }

    public function test_technician_can_browse_but_not_change_reference_data(): void
    {
        $this->actingAs($this->userByEmail('tech.pc@znu.test'));

        $this->get('/admin/rooms')->assertOk();
        $this->get('/admin/assets')->assertOk();
        $this->get('/admin/rooms/create')->assertForbidden();
        $this->get('/admin/assets/create')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/import-data')->assertForbidden();
    }

    public function test_supervisor_cannot_edit_a_super_admin(): void
    {
        $this->actingAs($this->userByEmail('supervisor@znu.test'));

        $this->get('/admin/users/'.$this->userByEmail('staff@znu.test')->id.'/edit')->assertOk();
        $this->get('/admin/users/'.$this->userByEmail('admin@znu.test')->id.'/edit')->assertForbidden();
    }

    public function test_requester_cannot_reach_admin_tools(): void
    {
        $this->actingAs($this->userByEmail('staff@znu.test'));

        $this->get(route('admin.rooms.qr', ['rooms' => [Room::first()->id]]))->assertForbidden();
        $this->get(route('admin.import-templates', 'locations'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomQrLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_labels_link_each_room_to_the_pwa_form(): void
    {
        config(['helpdesk.frontend_url' => 'https://it.example.test']);
        $room = Room::where('code', '105')->firstOrFail();

        $this->actingAs(User::where('email', 'tech.av@znu.test')->firstOrFail())
            ->get(route('admin.rooms.qr', ['rooms' => [$room->id]]))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('https://it.example.test/r/'.$room->qr_token, false)
            ->assertSee('قاعة 105');
    }

    public function test_building_sheet_contains_only_that_buildings_active_rooms(): void
    {
        $eng = Building::firstWhere('code', 'ENG');
        Room::where('code', 'LAB-3')->update(['is_active' => false]);

        $this->actingAs(User::where('email', 'supervisor@znu.test')->firstOrFail())
            ->get(route('admin.rooms.qr', ['building' => $eng->id]))
            ->assertOk()
            ->assertSee('قاعة 105')
            ->assertDontSee('معمل الحاسب 3')
            ->assertDontSee('مكتب شؤون الطلاب');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.rooms.qr', ['rooms' => [1]]))->assertRedirect('/admin/login');
    }
}

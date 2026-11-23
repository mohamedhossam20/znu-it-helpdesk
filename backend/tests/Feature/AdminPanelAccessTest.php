<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * @return array<string, array{string, int}>
     */
    public static function users(): array
    {
        return [
            'super admin' => ['admin@znu.test', 200],
            'it supervisor' => ['supervisor@znu.test', 200],
            'technician' => ['tech.pc@znu.test', 200],
            'requester' => ['staff@znu.test', 403],
        ];
    }

    #[DataProvider('users')]
    public function test_admin_panel_access_by_role(string $email, int $expectedStatus): void
    {
        $this->actingAs(User::where('email', $email)->firstOrFail())
            ->get('/admin')
            ->assertStatus($expectedStatus);
    }

    public function test_inactive_technician_is_locked_out(): void
    {
        $technician = User::where('email', 'tech.pc@znu.test')->firstOrFail();
        $technician->update(['is_active' => false]);

        $this->actingAs($technician)->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}

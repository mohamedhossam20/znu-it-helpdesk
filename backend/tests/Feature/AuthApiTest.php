<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const SPA_ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Origin' => self::SPA_ORIGIN, 'Referer' => self::SPA_ORIGIN.'/']);
    }

    private function demoPassword(): string
    {
        return env('DEMO_USER_PASSWORD', 'password');
    }

    public function test_user_can_log_in_and_receives_fixed_location(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'staff@znu.test', 'password' => $this->demoPassword()])
            ->assertOk()
            ->assertJsonPath('data.email', 'staff@znu.test')
            ->assertJsonPath('data.default_room.room.code', '204')
            ->assertJsonPath('data.default_room.building.code', 'MAIN')
            ->assertJsonPath('data.roles', ['requester']);

        $this->assertAuthenticatedAs(User::where('email', 'staff@znu.test')->first());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'staff@znu.test', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::where('email', 'staff@znu.test')->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@znu.test', 'password' => $this->demoPassword()])
            ->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_doctor_without_fixed_location_gets_null_room(): void
    {
        $doctor = User::where('email', 'doctor@znu.test')->firstOrFail();

        $this->actingAs($doctor)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.default_room', null);
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::where('email', 'staff@znu.test')->firstOrFail())
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }
}

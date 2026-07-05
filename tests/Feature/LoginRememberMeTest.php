<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginRememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_a_remember_cookie_when_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => bcrypt('password'),
            'status' => UserStatus::Active->value,
        ]);

        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/');
        $response->assertCookie(Auth::guard()->getRecallerName());

        $this->assertNotNull($user->fresh()->getRememberToken());
    }

    public function test_login_without_remember_does_not_issue_recaller_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'session-only@example.com',
            'password' => bcrypt('password'),
            'status' => UserStatus::Active->value,
        ]);

        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }
}

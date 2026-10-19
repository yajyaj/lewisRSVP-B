<?php

namespace Tests\Feature;

use App\Models\EventProject;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_csrf_middleware_rejects_forged_login_and_logout(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
        $user = User::factory()->create();

        $this->post('/admin/login', ['identifier' => $user->email, 'password' => 'password'])->assertStatus(419);
        $this->assertGuest();
        $this->actingAs($user)->post('/admin/logout')->assertStatus(419);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_guests_are_redirected_to_blade_login_and_cannot_write(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Sign in to Gather');
        $this->getJson('/api/admin/events')->assertUnauthorized();
    }

    public function test_blade_login_reuses_existing_identity_and_rotates_session(): void
    {
        $user = User::factory()->create(['username' => 'organizer']);
        $this->get('/admin/login');
        $sessionId = session()->getId();

        $this->post('/admin/login', ['identifier' => 'organizer', 'password' => 'password'])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseHas('admin_audit_events', ['actor_id' => $user->id, 'action' => 'auth.login']);
        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rejects_wrong_credentials_and_does_not_flash_password(): void
    {
        User::factory()->create(['username' => 'organizer']);

        $this->from('/admin/login')->post('/admin/login', ['identifier' => 'organizer', 'password' => 'wrong-password'])->assertRedirect('/admin/login')->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('admin_audit_events', 0);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_login_requires_identifier_and_password(): void
    {
        $this->post('/admin/login', [])->assertSessionHasErrors(['identifier', 'password']);
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', ['identifier' => 'unknown', 'password' => 'wrong'])->assertSessionHasErrors('identifier');
        }

        $this->post('/admin/login', ['identifier' => 'unknown', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_inactive_accounts_cannot_login_through_blade_or_api(): void
    {
        User::factory()->create(['username' => 'inactive', 'is_active' => false]);

        $this->post('/admin/login', ['identifier' => 'inactive', 'password' => 'password'])->assertSessionHasErrors('identifier');
        $this->postJson('/api/auth/login', ['identifier' => 'inactive', 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_deactivation_blocks_existing_blade_and_api_sessions(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/events')->assertForbidden();
        $this->actingAs($user)->getJson('/api/auth/user')->assertForbidden();
    }

    public function test_staff_cannot_access_users_and_untrusted_name_is_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee($user->name)->assertDontSee($user->name, false)->assertDontSee('title="Users"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_access_users_and_other_organizer_event(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();
        $event = $this->event($owner);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('title="Users"', false);
        $this->getJson('/api/admin/events/'.$event->id)->assertOk()->assertJsonPath('event.id', $event->id);
        $this->getJson('/api/admin/events')->assertOk()->assertJsonPath('events.0.id', $event->id);
    }

    public function test_staff_cannot_access_another_organizer_event(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $staff = User::factory()->create();

        $this->actingAs($staff)->getJson('/api/admin/events/'.$event->id)->assertNotFound();
        $this->deleteJson('/api/admin/events/'.$event->id)->assertNotFound();
        $this->assertModelExists($event);
    }

    public function test_unknown_role_is_denied_and_roles_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['role' => 'unknown']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $user->refresh()->fill(['role' => 'super_admin', 'is_active' => false]);
        $this->assertSame('unknown', $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_logout_invalidates_session_and_preserves_password_and_token_rows(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $user->id, 'name' => 'Existing token', 'token' => hash('sha256', 'fixture'), 'abilities' => '[]']);
        $this->actingAs($user)->get('/admin');
        $csrf = session()->token();

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->assertNotSame($csrf, session()->token());
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('admin_audit_events', ['actor_id' => $user->id, 'action' => 'auth.logout']);
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_bootstrap_promotes_only_selected_account_without_replacing_credentials(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $other = User::factory()->create();

        $this->artisan('rsvp:bootstrap-super-admin', ['identifier' => $user->email])->assertSuccessful();

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('staff', $other->fresh()->role);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'user.bootstrap_super_admin', 'subject_id' => $user->id]);
        $this->artisan('rsvp:bootstrap-super-admin', ['identifier' => $user->email])->assertSuccessful();
        $this->assertDatabaseCount('admin_audit_events', 1);
        $this->artisan('rsvp:bootstrap-super-admin', ['identifier' => $other->email])->assertFailed();
    }

    public function test_bootstrap_refuses_missing_and_inactive_accounts(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->artisan('rsvp:bootstrap-super-admin', ['identifier' => 'missing'])->assertFailed();
        $this->artisan('rsvp:bootstrap-super-admin', ['identifier' => $user->email])->assertFailed();
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    private function event(User $owner): EventProject
    {
        return $owner->eventProjects()->create([
            'internal_name' => 'Foundation QA', 'public_slug' => Str::uuid()->toString(),
            'combined_title' => 'Celebration', 'event_date' => '2027-01-09', 'timezone' => 'Asia/Manila',
        ]);
    }
}

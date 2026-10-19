<?php

namespace Tests\Feature;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUsersGuestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_staff_cannot_read_or_write_any_user_management_action(): void
    {
        $staff = User::factory()->create();
        $subject = User::factory()->create();
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/users/'.$subject->id)->assertForbidden();
        $this->get('/admin/users/'.$subject->id.'/edit')->assertForbidden();
        $this->post('/admin/users', $this->userData())->assertForbidden();
        $this->put('/admin/users/'.$subject->id, $this->userData($subject))->assertForbidden();
        $this->delete('/admin/users/'.$subject->id, $this->action($subject))->assertForbidden();
        $this->get('/admin/users/'.$subject->id.'/password')->assertForbidden();
        $this->put('/admin/users/'.$subject->id.'/password', $this->passwordData($subject))->assertForbidden();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_super_admin_creates_hashed_user_with_explicit_role_and_audit(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->post('/admin/users', $this->userData())->assertRedirect();
        $subject = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Strong-password-789!', $subject->password));
        $this->assertSame('staff', $subject->role);
        $this->assertTrue($subject->is_active);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'user.created', 'subject_id' => $subject->id]);
        $this->get('/admin/users/'.$subject->id)->assertOk()->assertDontSee($subject->password);
    }

    public function test_user_creation_requires_secure_confirmed_password_and_actor_password(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->post('/admin/users', array_merge($this->userData(), ['password' => 'weak', 'current_password' => 'wrong']))->assertSessionHasErrors(['password', 'current_password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_duplicate_and_ambiguous_login_identifiers_are_rejected(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'username' => 'reserved-name']);
        $this->actingAs($actor)->post('/admin/users', array_merge($this->userData(), ['email' => $actor->email]))->assertSessionHasErrors('email');
        $this->post('/admin/users', array_merge($this->userData(), ['username' => 'reserved-name']))->assertSessionHasErrors('username');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_editing_user_preserves_password_tokens_and_owned_event_records(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $event = $this->event($subject);
        $password = $subject->password;
        $this->actingAs($actor)->put('/admin/users/'.$subject->id, array_merge($this->userData($subject), ['name' => 'Renamed Organizer', 'role' => 'super_admin']))->assertRedirect();
        $this->assertSame('Renamed Organizer', $subject->fresh()->name);
        $this->assertTrue($subject->fresh()->isSuperAdmin());
        $this->assertSame($password, $subject->fresh()->password);
        $this->assertModelExists($event);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'user.updated']);
    }

    public function test_stale_user_edit_is_rejected_after_another_change(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $data = $this->userData($subject);
        $subject->name = 'Newer change';
        $subject->save();
        $this->actingAs($actor)->put('/admin/users/'.$subject->id, $data)->assertSessionHasErrors('version');
        $this->assertSame('Newer change', $subject->fresh()->name);
    }

    public function test_self_deactivation_and_last_super_admin_demotion_are_blocked(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->delete('/admin/users/'.$actor->id, $this->action($actor))->assertSessionHasErrors('is_active');
        $this->put('/admin/users/'.$actor->id, array_merge($this->userData($actor), ['is_active' => 0]))->assertSessionHasErrors('is_active');
        $this->put('/admin/users/'.$actor->id, array_merge($this->userData($actor), ['role' => 'staff']))->assertSessionHasErrors('role');
        $this->assertTrue($actor->fresh()->isSuperAdmin());
    }

    public function test_self_demotion_is_allowed_when_another_active_super_admin_remains(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $other = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($actor)->put('/admin/users/'.$actor->id, array_merge($this->userData($actor), ['role' => 'staff']))->assertRedirect('/admin');

        $this->assertSame('staff', $actor->fresh()->role);
        $this->assertTrue($other->fresh()->isSuperAdmin());
        $this->actingAs($actor->fresh())->get('/admin/users')->assertForbidden();
    }

    public function test_changing_own_password_signs_out_current_session(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($actor)->put('/admin/users/'.$actor->id.'/password', $this->passwordData($actor))->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->assertTrue(Hash::check('Strong-password-789!', $actor->fresh()->password));
    }

    public function test_deactivation_preserves_records_and_tokens_but_removes_only_target_sessions(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $event = $this->event($subject);
        $guest = $this->guest($event);
        $this->sessionRow('target-session', $subject);
        $this->sessionRow('actor-session', $actor);
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $subject->id, 'name' => 'Existing', 'token' => hash('sha256', 'fixture'), 'abilities' => '[]']);
        $this->actingAs($actor)->delete('/admin/users/'.$subject->id, $this->action($subject))->assertRedirect();
        $this->assertFalse($subject->fresh()->is_active);
        $this->assertModelExists($event);
        $this->assertModelExists($guest);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'actor-session']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->postJson('/api/auth/login', ['identifier' => $subject->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_deactivation_requires_confirmation_and_can_be_reactivated_by_super_admin(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $data = $this->action($subject);
        unset($data['confirm']);
        $this->actingAs($actor)->delete('/admin/users/'.$subject->id, $data)->assertSessionHasErrors('confirm');
        $subject->is_active = false;
        $subject->save();
        $this->put('/admin/users/'.$subject->id, array_merge($this->userData($subject), ['is_active' => 1]))->assertRedirect();
        $this->assertTrue($subject->fresh()->is_active);
    }

    public function test_password_change_is_separate_and_requires_actor_confirmation(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $password = $subject->password;
        $this->actingAs($actor)->put('/admin/users/'.$subject->id, array_merge($this->userData($subject), ['password' => 'Strong-password-789!']))->assertSessionHasErrors('password');
        $this->put('/admin/users/'.$subject->id.'/password', array_merge($this->passwordData($subject), ['current_password' => 'wrong']))->assertSessionHasErrors('current_password');
        $this->assertSame($password, $subject->fresh()->password);
    }

    public function test_password_change_hashes_new_password_and_invalidates_target_sessions_only(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $subject = User::factory()->create();
        $actorPassword = $actor->password;
        $this->sessionRow('target-session', $subject);
        $this->sessionRow('actor-session', $actor);
        $this->actingAs($actor)->put('/admin/users/'.$subject->id.'/password', $this->passwordData($subject))->assertRedirect();
        $this->assertTrue(Hash::check('Strong-password-789!', $subject->fresh()->password));
        $this->assertSame($actorPassword, $actor->fresh()->password);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'actor-session']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'user.password_changed', 'subject_id' => $subject->id]);
    }

    public function test_changed_password_invalidates_an_existing_cookie_session(): void
    {
        $subject = User::factory()->create();
        $this->actingAs($subject)->get('/admin')->assertOk();
        $subject->password = 'Different-password-789!';
        $subject->save();
        $this->actingAs($subject->fresh())->get('/admin')->assertForbidden();
        $this->assertGuest();
    }

    public function test_old_cookie_remains_invalid_after_account_is_reactivated(): void
    {
        $subject = User::factory()->create();
        $this->actingAs($subject)->get('/admin')->assertOk();
        AdminAuditEvent::create(['actor_id' => null, 'subject_id' => $subject->id, 'action' => 'user.deactivated']);

        $this->actingAs($subject->fresh())->get('/admin')->assertForbidden();
        $this->assertGuest();
    }

    public function test_password_forms_never_echo_previous_sensitive_input(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->withSession(['_old_input' => ['password' => 'Never-echo-secret-789!', 'current_password' => 'Never-echo-current-789!']])->get('/admin/users/create')->assertOk()->assertDontSee('Never-echo-secret-789!')->assertDontSee('Never-echo-current-789!');
    }

    public function test_user_filters_and_pagination_and_escaped_account_names(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'name' => '<script>name()</script>']);
        User::factory()->count(21)->create(['role' => 'staff']);
        $this->actingAs($actor)->get('/admin/users?role=staff&page=2')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 21 && $users->count() === 1);
        $this->get('/admin/users?role=super_admin')->assertOk()->assertSee($actor->name)->assertDontSee($actor->name, false);
    }

    public function test_guests_use_existing_records_with_owner_scoped_access(): void
    {
        $owner = User::factory()->create();
        $guest = $this->guest($this->event($owner));
        $other = User::factory()->create();
        $this->actingAs($owner)->get('/admin/guests')->assertOk()->assertSee($guest->full_name);
        $this->get('/admin/guests/'.$guest->id)->assertOk();
        $this->actingAs($other)->get('/admin/guests/'.$guest->id)->assertNotFound();
        $this->get('/admin/guests/'.$guest->id.'/edit')->assertNotFound();
        $this->put('/admin/guests/'.$guest->id, $this->guestData($guest))->assertNotFound();
        $this->get('/admin/guests?event='.$guest->event_project_id)->assertNotFound();
        $this->assertDatabaseCount('guests', 1);
    }

    public function test_guest_contact_edit_preserves_counts_and_invalidates_stale_reservation_form(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $guest = $this->guest($event);
        $response = $this->response($event, $guest);
        $this->actingAs($owner)->put('/admin/guests/'.$guest->id, array_merge($this->guestData($guest), ['full_name' => 'Updated Contact', 'event_project_id' => 999]))->assertRedirect();
        $this->assertSame('Updated Contact', $guest->fresh()->full_name);
        $this->assertSame($event->id, $guest->fresh()->event_project_id);
        $this->assertSame(2, $response->fresh()->revision);
        $this->assertSame(1, $response->fresh()->party_size);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'guest.updated', 'subject_id' => $guest->id]);
        $this->put('/admin/reservations/'.$response->id, ['full_name' => 'Stale Name', 'attendance_status' => 'attending', 'party_size' => 1, 'revision' => 1, 'change_reason' => 'Older form'])->assertSessionHasErrors('revision');
    }

    public function test_guest_edit_is_versioned_and_requires_valid_contact_and_reason(): void
    {
        $owner = User::factory()->create();
        $guest = $this->guest($this->event($owner));
        $data = $this->guestData($guest);
        $guest->full_name = 'Newer Contact';
        $guest->save();
        $this->actingAs($owner)->put('/admin/guests/'.$guest->id, $data)->assertSessionHasErrors('version');
        $this->put('/admin/guests/'.$guest->id, ['email' => 'invalid'])->assertSessionHasErrors(['full_name', 'email', 'version', 'change_reason']);
        $this->assertSame('Newer Contact', $guest->fresh()->full_name);
    }

    public function test_guest_filters_distinguish_responded_and_pending_active_invitations(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $guest = $this->guest($event);
        $this->response($event, $guest);
        $pending = $event->guests()->create(['full_name' => 'Pending Contact']);
        $invitation = new Invitation(['token_hash' => hash('sha256', 'private-fixture')]);
        $invitation->eventProject()->associate($event);
        $invitation->guest()->associate($pending);
        $invitation->save();
        $this->actingAs($owner)->get('/admin/guests?response=pending')->assertOk()->assertViewHas('guests', fn ($guests) => $guests->total() === 1 && $guests->first()->id === $pending->id);
        $this->get('/admin/guests?response=responded&search=Primary')->assertOk()->assertViewHas('guests', fn ($guests) => $guests->total() === 1);
    }

    public function test_super_admin_can_edit_guest_across_owners_and_html_is_escaped(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $guest = $this->guest($this->event(User::factory()->create()));
        $this->actingAs($actor)->put('/admin/guests/'.$guest->id, array_merge($this->guestData($guest), ['full_name' => '<script>guest()</script>']))->assertRedirect();
        $this->get('/admin/guests/'.$guest->id)->assertOk()->assertSee('<script>guest()</script>')->assertDontSee('<script>guest()</script>', false);
    }

    public function test_audit_failure_rolls_back_account_creation(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        AdminAuditEvent::creating(function (): void {
            throw new \RuntimeException('Simulated audit failure');
        });
        try {
            $this->actingAs($actor)->post('/admin/users', $this->userData())->assertStatus(500);
            $this->assertDatabaseCount('users', 1);
        } finally {
            AdminAuditEvent::flushEventListeners();
        }
    }

    public function test_each_navigation_uses_its_designated_view_folder(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->get('/admin')->assertOk()->assertViewIs('admin.dashboard.index');
        $this->get('/admin/users')->assertOk()->assertViewIs('admin.users.index');
        $this->get('/admin/guests')->assertOk()->assertViewIs('admin.guests.index');
        $this->get('/admin/event-settings')->assertOk()->assertViewIs('admin.event-settings.index');
        $this->get('/admin/profile')->assertOk()->assertViewIs('admin.profile.index');
    }

    private function userData(?User $subject = null): array
    {
        $data = ['name' => $subject?->name ?? 'New Organizer', 'email' => $subject?->email ?? 'new@example.test', 'username' => $subject?->username ?? 'new-organizer', 'role' => $subject?->role ?? 'staff', 'is_active' => (int) ($subject?->is_active ?? true), 'current_password' => 'password', 'change_reason' => 'Approved organizer access'];
        if ($subject) {
            $data['version'] = $subject->editVersion();
        } else {
            $data['password'] = 'Strong-password-789!';
            $data['password_confirmation'] = $data['password'];
        }

        return $data;
    }

    private function passwordData(User $subject): array
    {
        return ['current_password' => 'password', 'password' => 'Strong-password-789!', 'password_confirmation' => 'Strong-password-789!', 'version' => $subject->editVersion(), 'change_reason' => 'Organizer requested a reset'];
    }

    private function action(User $subject): array
    {
        return ['current_password' => 'password', 'version' => $subject->editVersion(), 'change_reason' => 'Access no longer needed', 'confirm' => 1];
    }

    private function guestData(Guest $guest): array
    {
        return ['full_name' => $guest->full_name, 'email' => $guest->email, 'phone' => $guest->phone, 'version' => $guest->editVersion(), 'change_reason' => 'Contact correction requested'];
    }

    private function event(User $owner): EventProject
    {
        return $owner->eventProjects()->create(['internal_name' => 'QA Celebration', 'public_slug' => Str::uuid()->toString(), 'combined_title' => 'QA Birthday', 'event_date' => '2027-01-09', 'timezone' => 'Asia/Manila']);
    }

    private function guest(EventProject $event): Guest
    {
        return $event->guests()->create(['full_name' => 'Primary Contact', 'email' => 'guest@example.test']);
    }

    private function response(EventProject $event, Guest $guest): RsvpResponse
    {
        $response = new RsvpResponse(['attendance_status' => 'attending', 'party_size' => 1, 'submitted_at' => now()]);
        $response->eventProject()->associate($event);
        $response->guest()->associate($guest);
        $response->save();

        return $response->fresh();
    }

    private function sessionRow(string $id, User $user): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => 'fixture', 'last_activity' => now()->timestamp]);
    }
}

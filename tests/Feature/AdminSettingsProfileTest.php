<?php

namespace Tests\Feature;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSettingsProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_staff_can_create_an_event_and_public_api_consumes_saved_details(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/admin/event-settings', $this->eventData())->assertRedirect();
        $event = EventProject::firstOrFail();
        $this->assertSame($owner->id, $event->owner_id);
        $this->assertSame('published', $event->status);
        $this->assertDatabaseHas('admin_audit_events', ['actor_id' => $owner->id, 'subject_id' => $event->id, 'action' => 'event.created']);
        $this->getJson('/api/events/'.$event->public_slug)->assertOk()->assertJsonPath('event.venue_name', 'QA venue')->assertJsonPath('event.occasion_labels.0', 'Christening')->assertJsonPath('event.max_party_size', 1);
    }

    public function test_event_updates_preserve_owned_guest_and_token_records(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $guest = $event->guests()->create(['full_name' => 'Existing Guest']);
        $invitation = $this->invitation($event, $guest);
        $data = array_merge($this->eventData($event), ['combined_title' => 'Changed celebration', 'venue_name' => 'Updated venue']);
        $this->actingAs($owner)->put('/admin/event-settings/'.$event->id, $data)->assertRedirect();
        $this->assertSame('Changed celebration', $event->fresh()->combined_title);
        $this->assertModelExists($guest);
        $this->assertSame($invitation->token_hash, $invitation->fresh()->token_hash);
        $this->getJson('/api/events/'.$event->public_slug)->assertOk()->assertJsonPath('event.venue_name', 'Updated venue');
    }

    public function test_staff_cannot_read_or_write_another_organizer_event(): void
    {
        $event = $this->event(User::factory()->create());
        $staff = User::factory()->create();
        $this->actingAs($staff)->get('/admin/event-settings/'.$event->id.'/edit')->assertNotFound();
        $this->put('/admin/event-settings/'.$event->id, $this->eventData($event))->assertNotFound();
        $this->get('/admin/event-settings')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 0);
    }

    public function test_super_admin_can_update_another_organizer_event_without_changing_owner(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->put('/admin/event-settings/'.$event->id, array_merge($this->eventData($event), ['owner_id' => $admin->id]))->assertRedirect();
        $this->assertSame($owner->id, $event->fresh()->owner_id);
    }

    public function test_guest_cannot_access_settings_or_profile_writes(): void
    {
        $this->get('/admin/event-settings')->assertRedirect('/admin/login');
        $this->post('/admin/event-settings', $this->eventData())->assertRedirect('/admin/login');
        $this->put('/admin/profile', [])->assertRedirect('/admin/login');
        $this->put('/admin/profile/password', [])->assertRedirect('/admin/login');
        $this->assertDatabaseCount('event_projects', 0);
    }

    public function test_local_deadline_uses_event_timezone_and_roundtrips_to_public_api(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/admin/event-settings', array_merge($this->eventData(), ['rsvp_deadline_local' => '2027-01-08T18:30']))->assertRedirect();
        $event = EventProject::firstOrFail();
        $this->assertSame('2027-01-08 10:30', $event->rsvp_deadline->utc()->format('Y-m-d H:i'));
        $this->get('/admin/event-settings/'.$event->id.'/edit')->assertOk()->assertSee('2027-01-08T18:30');
        $this->getJson('/api/events/'.$event->public_slug)->assertOk()->assertJsonPath('event.rsvp_deadline', '2027-01-08T10:30:00+00:00');
    }

    public function test_nonexistent_daylight_saving_deadline_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/admin/event-settings', array_merge($this->eventData(), ['timezone' => 'America/New_York', 'rsvp_deadline_local' => '2026-03-08T02:30']))->assertSessionHasErrors('rsvp_deadline_local');
        $this->assertDatabaseCount('event_projects', 0);
    }

    public function test_invalid_time_order_timezone_and_unsafe_urls_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/admin/event-settings', array_merge($this->eventData(), ['start_time' => '15:00', 'end_time' => '10:00']))->assertSessionHasErrors('end_time');
        $this->post('/admin/event-settings', array_merge($this->eventData(), ['timezone' => 'Invalid/Zone', 'map_url' => 'javascript:alert(1)']))->assertSessionHasErrors(['timezone', 'map_url']);
        $this->assertDatabaseCount('event_projects', 0);
    }

    public function test_address_length_matches_existing_schema_and_reason_is_required(): void
    {
        $owner = User::factory()->create();
        $data = $this->eventData();
        unset($data['change_reason']);
        $this->actingAs($owner)->post('/admin/event-settings', array_merge($data, ['venue_address' => str_repeat('x', 256)]))->assertSessionHasErrors('venue_address');
    }

    public function test_stale_settings_are_rejected_after_legacy_api_changes(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $data = $this->eventData($event);
        $api = $this->eventData($event);
        $api['combined_title'] = 'New API title';
        $this->actingAs($owner)->putJson('/api/admin/events/'.$event->id, $api)->assertOk();
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasErrors('version');
        $this->assertSame('New API title', $event->fresh()->combined_title);
    }

    public function test_switching_private_to_public_requires_explicit_confirmation(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['access_mode' => 'private']);
        $guest = $event->guests()->create(['full_name' => 'Private Guest']);
        $invitation = $this->invitation($event, $guest);
        $data = array_merge($this->eventData($event), ['access_mode' => 'public']);
        $this->actingAs($owner)->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasErrors('confirm_revoke');
        $this->assertSame('active', $invitation->fresh()->status);
        $this->put('/admin/event-settings/'.$event->id, array_merge($data, ['confirm_revoke' => 1]))->assertRedirect();
        $this->assertSame('revoked', $invitation->fresh()->status);
        $this->assertSame($invitation->token_hash, $invitation->fresh()->token_hash);
    }

    public function test_draft_and_archived_updates_keep_public_invitation_unavailable(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $this->actingAs($owner)->put('/admin/event-settings/'.$event->id, array_merge($this->eventData($event), ['status' => 'archived']))->assertRedirect();
        $this->getJson('/api/events/'.$event->public_slug)->assertNotFound();
    }

    public function test_event_settings_escape_content_and_do_not_expose_invitation_hashes(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['combined_title' => '<script>title()</script>']);
        $guest = $event->guests()->create(['full_name' => 'Guest']);
        $invitation = $this->invitation($event, $guest);
        $this->actingAs($owner)->get('/admin/event-settings/'.$event->id.'/edit')->assertOk()->assertSee($event->combined_title)->assertDontSee($event->combined_title, false)->assertDontSee($invitation->token_hash);
    }

    public function test_labels_and_gallery_are_validated_and_can_be_cleared_explicitly(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $event->occasionLabels()->create(['label' => 'Existing occasion']);
        $event->gallery_urls = ['https://example.test/old.jpg'];
        $event->save();
        $event = $event->fresh();
        $this->actingAs($owner)->put('/admin/event-settings/'.$event->id, array_merge($this->eventData($event), ['occasion_labels_text' => '', 'gallery_urls_text' => '']))->assertRedirect();
        $this->assertSame([], $event->fresh()->gallery_urls);
        $this->assertSame(0, $event->occasionLabels()->count());
    }

    public function test_profile_updates_own_name_email_but_not_credentials_or_role(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $this->actingAs($user)->put('/admin/profile', array_merge($this->profileData($user), ['name' => 'Updated Profile', 'email' => 'updated@example.test']))->assertRedirect('/admin/profile');
        $this->assertSame('Updated Profile', $user->fresh()->name);
        $this->assertSame('updated@example.test', $user->fresh()->email);
        $this->assertSame('staff', $user->fresh()->role);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'profile.updated', 'actor_id' => $user->id]);
    }

    public function test_profile_rejects_role_status_or_target_injection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/admin/profile', array_merge($this->profileData($user), ['role' => 'super_admin', 'is_active' => false, 'user_id' => 999]))->assertSessionHasErrors(['role', 'is_active', 'user_id']);
        $this->assertSame('staff', $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_profile_requires_current_password_and_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->put('/admin/profile', array_merge($this->profileData($user), ['current_password' => 'wrong']))->assertSessionHasErrors('current_password');
        $this->put('/admin/profile', array_merge($this->profileData($user), ['email' => $other->email]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_profile_rejects_stale_account_state(): void
    {
        $user = User::factory()->create();
        $data = $this->profileData($user);
        $user->name = 'Changed elsewhere';
        $user->save();
        $this->actingAs($user->fresh())->put('/admin/profile', $data)->assertSessionHasErrors('version');
        $this->assertSame('Changed elsewhere', $user->fresh()->name);
    }

    public function test_profile_password_change_logs_out_only_own_account_and_preserves_token_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherPassword = $other->password;
        DB::table('sessions')->insert([['id' => 'own-session', 'user_id' => $user->id, 'payload' => 'fixture', 'last_activity' => now()->timestamp], ['id' => 'other-session', 'user_id' => $other->id, 'payload' => 'fixture', 'last_activity' => now()->timestamp]]);
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $user->id, 'name' => 'Existing', 'token' => hash('sha256', 'existing-fixture'), 'abilities' => '[]']);
        $this->actingAs($user)->put('/admin/profile/password', $this->passwordData($user))->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertTrue(Hash::check('Updated-password-789!', $user->fresh()->password));
        $this->assertSame($otherPassword, $other->fresh()->password);
        $this->assertDatabaseMissing('sessions', ['id' => 'own-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'user.password_changed', 'subject_id' => $user->id]);
    }

    public function test_profile_password_change_requires_confirmation_and_current_password(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $this->actingAs($user)->put('/admin/profile/password', array_merge($this->passwordData($user), ['password_confirmation' => 'mismatch', 'current_password' => 'wrong']))->assertSessionHasErrors(['password', 'current_password']);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_profile_forms_have_unique_input_ids_and_escape_names(): void
    {
        $user = User::factory()->create(['name' => '<script>profile()</script>']);
        $this->actingAs($user)->get('/admin/profile')->assertOk()->assertSee($user->name)->assertDontSee($user->name, false)->assertSee('id="profile-current-password"', false)->assertSee('id="password-current-password"', false);
    }

    public function test_audit_failure_rolls_back_profile_and_event_changes(): void
    {
        $user = User::factory()->create();
        $event = $this->event($user);
        $name = $user->name;
        AdminAuditEvent::creating(function (): void {
            throw new \RuntimeException('Simulated audit failure');
        });
        try {
            $this->actingAs($user)->put('/admin/profile', array_merge($this->profileData($user), ['name' => 'Must roll back']))->assertStatus(500);
            $this->put('/admin/event-settings/'.$event->id, array_merge($this->eventData($event), ['combined_title' => 'Must roll back']))->assertStatus(500);
            $this->assertSame($name, $user->fresh()->name);
            $this->assertSame('QA Birthday', $event->fresh()->combined_title);
        } finally {
            AdminAuditEvent::flushEventListeners();
        }
    }

    public function test_simplified_event_form_preserves_omitted_settings_and_images(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['status' => 'draft', 'access_mode' => 'private', 'theme' => 'blue', 'allow_plus_ones' => true, 'max_party_size' => 4, 'cover_image_url' => 'https://example.test/cover.jpg', 'gallery_urls' => ['https://example.test/photo.jpg']]);
        $data = $this->eventData($event);
        foreach (['status', 'access_mode', 'theme', 'allow_plus_ones', 'max_party_size', 'gallery_urls_text', 'change_reason'] as $field) {
            unset($data[$field]);
        }
        $this->actingAs($owner)->get('/admin/event-settings/'.$event->id.'/edit')->assertOk()->assertDontSee('Publishing &amp; replies', false)->assertDontSee('Appearance &amp; images', false)->assertDontSee('name="change_reason"', false);
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $saved = $event->fresh();
        foreach (['status', 'access_mode', 'theme', 'allow_plus_ones', 'max_party_size', 'cover_image_url', 'gallery_urls'] as $field) {
            $this->assertSame($event->$field, $saved->$field);
        }
        $this->assertDatabaseHas('admin_audit_events', ['subject_id' => $event->id, 'action' => 'event.updated', 'reason' => 'Saved through Event Settings.']);
    }

    public function test_simplified_create_defaults_to_public_one_guest_event(): void
    {
        $owner = User::factory()->create();
        $data = $this->eventData();
        foreach (['status', 'access_mode', 'theme', 'allow_plus_ones', 'max_party_size', 'gallery_urls_text', 'change_reason'] as $field) {
            unset($data[$field]);
        }
        $this->actingAs($owner)->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('event_projects', ['status' => 'published', 'access_mode' => 'public', 'allow_plus_ones' => false, 'max_party_size' => 1]);
    }

    public function test_event_slug_is_automatic_on_create_and_preserved_on_edit(): void
    {
        $owner = User::factory()->create();
        $data = $this->eventData();
        unset($data['public_slug']);
        $this->actingAs($owner)->get('/admin/event-settings/create')->assertOk()->assertDontSee('name="public_slug"', false);
        $this->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $event = EventProject::firstOrFail();
        $slug = $event->public_slug;
        $this->assertMatchesRegularExpression('/^event-[a-f0-9-]+$/', $slug);
        $update = $this->eventData($event);
        unset($update['public_slug']);
        $this->put('/admin/event-settings/'.$event->id, $update)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($slug, $event->fresh()->public_slug);
        $this->getJson('/api/events/'.$slug)->assertOk();
        $this->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, EventProject::query()->distinct()->count('public_slug'));
    }

    public function test_two_occasions_save_independent_venues_and_public_details(): void
    {
        $owner = User::factory()->create();
        $data = $this->eventData();
        $data['event_stops'] = [
            ['label' => 'Christening', 'venue_name' => 'Church', 'venue_address' => 'Church address', 'map_url' => 'https://maps.example.test/church', 'start_time' => '10:00', 'end_time' => '11:00'],
            ['label' => '1st Birthday', 'venue_name' => 'Restaurant', 'venue_address' => 'Restaurant address', 'map_url' => 'https://maps.example.test/party', 'start_time' => '11:30', 'end_time' => '15:00'],
        ];
        $this->actingAs($owner)->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $event = EventProject::firstOrFail();
        $this->assertSame($data['event_stops'], $event->event_stops);
        $this->assertSame('Church', $event->venue_name);
        $this->getJson('/api/events/'.$event->public_slug)->assertOk()->assertJsonPath('event.event_stops.1.venue_name', 'Restaurant')->assertJsonPath('event.occasion_labels.1', '1st Birthday');
        $this->get('/admin/event-settings/'.$event->id.'/edit')->assertOk()->assertSee('Restaurant')->assertSee('Church');
    }

    public function test_second_occasion_can_be_blank_and_invalid_stops_are_rejected(): void
    {
        $owner = User::factory()->create();
        $data = $this->eventData();
        $data['event_stops'] = [['label' => 'Christening', 'venue_name' => 'Church'], ['label' => '', 'venue_name' => '']];
        $this->actingAs($owner)->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertCount(1, EventProject::firstOrFail()->event_stops);
        $data['public_slug'] = 'invalid-occasion';
        $data['event_stops'] = [['label' => 'Birthday', 'start_time' => '15:00', 'end_time' => '10:00']];
        $this->post('/admin/event-settings', $data)->assertSessionHasErrors('event_stops.0.end_time');
        $data['event_stops'] = [['venue_name' => 'Missing name']];
        $this->post('/admin/event-settings', $data)->assertSessionHasErrors('event_stops.0.label');
        $data['event_stops'] = array_fill(0, 3, ['label' => 'Too many']);
        $this->post('/admin/event-settings', $data)->assertSessionHasErrors('event_stops');
        $this->assertDatabaseCount('event_projects', 1);
    }

    private function event(User $owner, array $attributes = []): EventProject
    {
        return $owner->eventProjects()->create(array_merge(['internal_name' => 'QA Celebration', 'combined_title' => 'QA Birthday', 'public_slug' => 'qa-'.Str::uuid()->toString(), 'event_date' => '2027-01-09', 'start_time' => '10:00', 'end_time' => '15:00', 'timezone' => 'Asia/Manila', 'status' => 'published', 'access_mode' => 'public', 'allow_plus_ones' => false, 'max_party_size' => 1], $attributes))->fresh();
    }

    private function eventData(?EventProject $event = null): array
    {
        $data = ['internal_name' => 'QA Celebration', 'combined_title' => 'QA Birthday', 'public_slug' => $event?->public_slug ?? 'qa-new-event', 'event_date' => '2027-01-09', 'start_time' => '10:00', 'end_time' => '15:00', 'timezone' => 'Asia/Manila', 'venue_name' => 'QA venue', 'venue_address' => 'QA address', 'theme' => 'sage', 'status' => 'published', 'access_mode' => $event?->access_mode ?? 'public', 'allow_plus_ones' => 0, 'max_party_size' => 1, 'occasion_labels_text' => 'Christening', 'gallery_urls_text' => '', 'change_reason' => 'Approved event details'];
        if ($event) {
            $data['version'] = $event->editVersion();
        }

        return $data;
    }

    private function profileData(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'current_password' => 'password', 'version' => $user->editVersion()];
    }

    private function passwordData(User $user): array
    {
        return ['password' => 'Updated-password-789!', 'password_confirmation' => 'Updated-password-789!', 'current_password' => 'password', 'version' => $user->editVersion()];
    }

    private function invitation(EventProject $event, Guest $guest): Invitation
    {
        $invitation = new Invitation(['token_hash' => hash('sha256', 'private-fixture')]);
        $invitation->eventProject()->associate($event);
        $invitation->guest()->associate($guest);
        $invitation->save();

        return $invitation;
    }
}

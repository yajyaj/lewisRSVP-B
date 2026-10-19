<?php

namespace Tests\Feature;

use App\Models\AdminAuditEvent;
use App\Models\EventProject;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReservationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_reconciles_parties_people_cancellations_and_pending(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $this->response($event, ['party_size' => 3]);
        $this->response($event, ['attendance_status' => 'declined', 'party_size' => 0]);
        $this->response($event, ['cancelled_at' => now(), 'party_size' => 4]);
        $guest = $event->guests()->create(['full_name' => 'Invited Contact']);
        $this->invitation($event, $guest, 'pending-fixture');
        $other = $this->event(User::factory()->create());
        $this->response($other, ['party_size' => 10]);

        $this->actingAs($owner)->get('/admin')->assertOk()->assertViewHas('stats', fn ($stats) => (int) $stats->total === 3 && (int) $stats->attending === 1 && (int) $stats->attendees === 3 && (int) $stats->cancelled === 1 && (int) $stats->declined === 1)->assertViewHas('pending', 1);
        $this->getJson('/api/admin/events/'.$event->id.'/metrics')->assertOk()->assertJsonPath('metrics.submissions', 2)->assertJsonPath('metrics.confirmed_attendees', 3);
    }

    public function test_super_admin_can_filter_dashboard_across_owners(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $event = $this->event(User::factory()->create());
        $this->response($event);

        $this->actingAs($admin)->get('/admin?event='.$event->id)->assertOk()->assertViewHas('stats', fn ($stats) => (int) $stats->total === 1);
    }

    public function test_guest_cannot_read_or_write_reservations(): void
    {
        $event = $this->event(User::factory()->create());
        $response = $this->response($event);

        $this->get('/admin/reservations')->assertRedirect('/admin/login');
        $this->post('/admin/reservations', $this->payload($event))->assertRedirect('/admin/login');
        $this->delete('/admin/reservations/'.$response->id, $this->action($response))->assertRedirect('/admin/login');
        $this->assertNull($response->fresh()->cancelled_at);
    }

    public function test_staff_cannot_access_other_owner_records_or_create_for_other_events(): void
    {
        $event = $this->event(User::factory()->create());
        $response = $this->response($event);
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/admin/reservations/'.$response->id)->assertNotFound();
        $this->get('/admin/reservations/'.$response->id.'/edit')->assertNotFound();
        $this->put('/admin/reservations/'.$response->id, $this->editData($response))->assertNotFound();
        $this->delete('/admin/reservations/'.$response->id, $this->action($response))->assertNotFound();
        $this->post('/admin/reservations', $this->payload($event))->assertNotFound();
        $this->get('/admin?event='.$event->id)->assertNotFound();
        $this->get('/admin/reservations?event='.$event->id)->assertNotFound();
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_creating_reservation_is_idempotent_and_audited_without_identity_hash_leaks(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $payload = $this->payload($event);

        $this->actingAs($owner)->post('/admin/reservations', $payload)->assertRedirect();
        $response = RsvpResponse::firstOrFail();
        $this->post('/admin/reservations', $payload)->assertRedirect('/admin/reservations/'.$response->id);

        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('admin_audit_events', 1);
        $this->assertDatabaseHas('admin_audit_events', ['subject_id' => $response->id, 'action' => 'reservation.created', 'reason' => 'Recorded by telephone']);
        $this->get('/admin/reservations/'.$response->id)->assertOk()->assertDontSee($response->public_response_key_hash)->assertSee('Recorded by telephone');
    }

    public function test_new_admin_reservations_enforce_one_guest_and_reject_invalid_status(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);

        $this->actingAs($owner)->post('/admin/reservations', array_merge($this->payload($event), ['party_size' => 2]))->assertSessionHasErrors('party_size');
        $this->post('/admin/reservations', array_merge($this->payload($event), ['attendance_status' => 'pending']))->assertSessionHasErrors('attendance_status');
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertDatabaseCount('guests', 0);
    }

    public function test_create_requires_valid_contact_change_reason_and_submission_key(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);

        $this->actingAs($owner)->post('/admin/reservations', ['event_project_id' => $event->id, 'email' => 'invalid'])->assertSessionHasErrors(['full_name', 'attendance_status', 'party_size', 'change_reason', 'submission_key', 'email']);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_admin_edits_contact_and_response_transactionally_and_ignores_unexpected_keys(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner));
        $data = array_merge($this->editData($response), ['full_name' => 'Changed Contact', 'attendance_status' => 'declined', 'cancelled_at' => now()->toDateTimeString(), 'public_response_key_hash' => 'injected']);
        $hash = $response->public_response_key_hash;

        $this->actingAs($owner)->put('/admin/reservations/'.$response->id, $data)->assertRedirect('/admin/reservations/'.$response->id);

        $response->refresh();
        $this->assertSame('Changed Contact', $response->guest->full_name);
        $this->assertSame('declined', $response->attendance_status);
        $this->assertSame(0, $response->party_size);
        $this->assertSame(2, $response->revision);
        $this->assertNull($response->cancelled_at);
        $this->assertSame($hash, $response->public_response_key_hash);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'reservation.updated', 'subject_id' => $response->id]);
    }

    public function test_stale_edit_does_not_overwrite_a_newer_public_response(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $key = Str::uuid()->toString();
        $response = $this->response($event, ['public_response_key_hash' => hash('sha256', $key)]);
        $old = $this->editData($response);
        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', ['full_name' => 'New guest reply', 'attendance_status' => 'attending', 'party_size' => 1, 'response_key' => $key])->assertCreated();

        $this->actingAs($owner)->put('/admin/reservations/'.$response->id, $old)->assertSessionHasErrors('revision');

        $this->assertSame('New guest reply', $response->fresh()->guest->full_name);
        $this->assertSame(2, $response->fresh()->revision);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'reservation.updated']);
    }

    public function test_cancellation_retains_contact_invitation_response_and_original_status(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $response = $this->response($event);
        $invitation = $this->invitation($event, $response->guest, 'preserved-token');
        $response->invitation_id = $invitation->id;
        $response->save();

        $this->actingAs($owner)->delete('/admin/reservations/'.$response->id, $this->action($response))->assertRedirect('/admin/reservations/'.$response->id);

        $this->assertNotNull($response->fresh()->cancelled_at);
        $this->assertSame('attending', $response->fresh()->attendance_status);
        $this->assertModelExists($response->guest);
        $this->assertSame('active', $invitation->fresh()->status);
        $this->assertSame(hash('sha256', 'preserved-token'), $invitation->fresh()->token_hash);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'reservation.cancelled', 'subject_id' => $response->id]);
        $this->getJson('/api/admin/events/'.$event->id.'/responses')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_cancellation_requires_confirmation_reason_and_current_revision(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner));

        $this->actingAs($owner)->delete('/admin/reservations/'.$response->id, [])->assertSessionHasErrors(['confirm', 'change_reason', 'revision']);
        $this->delete('/admin/reservations/'.$response->id, array_merge($this->action($response), ['revision' => 99]))->assertSessionHasErrors('revision');
        $this->assertNull($response->fresh()->cancelled_at);
    }

    public function test_public_resubmission_cannot_silently_reopen_a_cancelled_reservation(): void
    {
        $event = $this->event(User::factory()->create());
        $key = Str::uuid()->toString();
        $response = $this->response($event, ['cancelled_at' => now(), 'public_response_key_hash' => hash('sha256', $key)]);

        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', ['full_name' => 'Attempted update', 'attendance_status' => 'attending', 'party_size' => 1, 'response_key' => $key])->assertUnprocessable()->assertJsonValidationErrors('rsvp');

        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertNotNull($response->fresh()->cancelled_at);
    }

    public function test_restoration_is_explicit_audited_and_restores_attendee_totals(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $response = $this->response($event, ['cancelled_at' => now(), 'party_size' => 3]);

        $this->actingAs($owner)->post('/admin/reservations/'.$response->id.'/restore', $this->action($response))->assertRedirect('/admin/reservations/'.$response->id);

        $this->assertNull($response->fresh()->cancelled_at);
        $this->assertSame(3, $response->fresh()->party_size);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'reservation.restored']);
        $this->getJson('/api/admin/events/'.$event->id.'/metrics')->assertJsonPath('metrics.confirmed_attendees', 3);
    }

    public function test_cancelled_records_cannot_be_edited_or_cancelled_twice(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner), ['cancelled_at' => now()]);

        $this->actingAs($owner)->put('/admin/reservations/'.$response->id, $this->editData($response))->assertSessionHasErrors('reservation');
        $this->delete('/admin/reservations/'.$response->id, $this->action($response))->assertSessionHasErrors('reservation');
        $this->assertSame(1, $response->fresh()->revision);
    }

    public function test_existing_group_size_can_be_preserved_after_event_limit_is_lowered(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['allow_plus_ones' => false, 'max_party_size' => 1]);
        $response = $this->response($event, ['party_size' => 3, 'additional_guest_names' => ['Companion One', 'Companion Two']]);

        $this->actingAs($owner)->put('/admin/reservations/'.$response->id, $this->editData($response))->assertRedirect();

        $this->assertSame(3, $response->fresh()->party_size);
        $this->assertSame(['Companion One', 'Companion Two'], $response->fresh()->additional_guest_names);
    }

    public function test_reducing_existing_group_can_clear_additional_names_explicitly(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner), ['party_size' => 3, 'additional_guest_names' => ['Companion One', 'Companion Two']]);

        $this->actingAs($owner)->get('/admin/reservations/'.$response->id.'/edit')->assertOk()->assertSee('Companion One');

        $this->put('/admin/reservations/'.$response->id, array_merge($this->editData($response), ['party_size' => 1, 'additional_guest_names_text' => '']))->assertRedirect();

        $this->assertSame(1, $response->fresh()->party_size);
        $this->assertSame([], $response->fresh()->additional_guest_names);
    }

    public function test_name_reference_status_and_date_filters_do_not_leak_other_events(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $wanted = $this->response($event, ['submitted_at' => now()->subDays(2)]);
        $wanted->guest->update(['full_name' => 'Matching Guest']);
        $this->response($event, ['attendance_status' => 'declined', 'party_size' => 0]);
        $this->response($this->event(User::factory()->create()));

        $this->actingAs($owner)->get('/admin/reservations?search='.urlencode($wanted->reference))->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $wanted->id);
        $this->get('/admin/reservations?search=Matching&status=attending&date_to='.now()->subDay()->toDateString())->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 1);
    }

    public function test_reservation_list_paginates_and_rejects_unsafe_sort_or_date_filters(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        for ($i = 0; $i < 22; $i++) {
            $this->response($event);
        }

        $this->actingAs($owner)->get('/admin/reservations?sort=oldest&page=2')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 22 && $rows->count() === 2);
        $this->get('/admin/reservations?sort='.urlencode('id desc; DROP TABLE users'))->assertSessionHasErrors('sort');
        $this->get('/admin/reservations?date_from=2027-02-01&date_to=2027-01-01')->assertSessionHasErrors('date_to');
        $this->assertDatabaseCount('rsvp_responses', 22);
    }

    public function test_details_escape_names_notes_messages_and_change_reasons(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner), ['message' => '<script>message()</script>', 'dietary_or_accessibility_notes' => '<script>notes()</script>']);
        $response->guest->update(['full_name' => '<script>name()</script>']);
        AdminAuditEvent::create(['actor_id' => $owner->id, 'subject_id' => $response->id, 'action' => 'reservation.updated', 'reason' => '<script>reason()</script>']);

        $result = $this->actingAs($owner)->get('/admin/reservations/'.$response->id)->assertOk();
        foreach (['name', 'message', 'notes', 'reason'] as $field) {
            $result->assertSee('<script>'.$field.'()</script>')->assertDontSee('<script>'.$field.'()</script>', false);
        }
    }

    public function test_legacy_api_edits_share_rules_and_audit_without_changing_contract(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner));
        $data = $this->editData($response);
        unset($data['revision'], $data['change_reason']);

        $this->actingAs($owner)->putJson('/api/admin/responses/'.$response->id, $data)->assertOk()->assertJsonPath('response.guest.full_name', $data['full_name']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'reservation.updated', 'reason' => 'Updated through the existing organizer API.']);
        $this->assertSame(2, $response->fresh()->revision);
    }

    public function test_failed_audit_write_rolls_back_guest_and_response_changes(): void
    {
        $owner = User::factory()->create();
        $response = $this->response($this->event($owner));
        $original = $response->guest->full_name;
        AdminAuditEvent::creating(function (): void {
            throw new \RuntimeException('Simulated audit storage failure');
        });
        try {
            $this->actingAs($owner)->put('/admin/reservations/'.$response->id, array_merge($this->editData($response), ['full_name' => 'Must roll back']))->assertStatus(500);
            $this->assertSame($original, $response->fresh()->guest->full_name);
            $this->assertSame(1, $response->fresh()->revision);
        } finally {
            AdminAuditEvent::flushEventListeners();
        }
    }

    public function test_deleted_reservations_leave_the_default_list_and_can_be_found_for_restore(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $active = $this->response($event);
        $deleted = $this->response($event);
        $this->actingAs($owner)->get('/admin/reservations')->assertOk()->assertSee('#delete-reservation', false);
        $this->get('/admin/reservations/'.$deleted->id)->assertOk()->assertSee('Delete reservation')->assertSee('name="confirm"', false);
        $this->delete('/admin/reservations/'.$deleted->id, $this->action($deleted))->assertRedirect();
        $this->get('/admin/reservations')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->modelKeys() === [$active->id]);
        $this->get('/admin/reservations?status=cancelled')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->modelKeys() === [$deleted->id]);
        $this->post('/admin/reservations/'.$deleted->id.'/restore', $this->action($deleted->fresh()))->assertRedirect();
        $this->get('/admin/reservations')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 2);
    }

    private function event(User $owner, array $overrides = []): EventProject
    {
        return $owner->eventProjects()->create(array_merge(['internal_name' => 'QA Celebration', 'public_slug' => Str::uuid()->toString(), 'combined_title' => 'QA Birthday', 'event_date' => '2027-01-09', 'timezone' => 'Asia/Manila', 'status' => 'published', 'access_mode' => 'public', 'allow_plus_ones' => true, 'max_party_size' => 4], $overrides));
    }

    private function response(EventProject $event, array $overrides = []): RsvpResponse
    {
        $guest = $event->guests()->create(['full_name' => 'QA Guest', 'email' => 'guest@example.test']);
        $response = new RsvpResponse(array_merge(['attendance_status' => 'attending', 'party_size' => 1, 'submitted_at' => now()], $overrides));
        foreach (['cancelled_at', 'public_response_key_hash'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $response->$key = $overrides[$key];
            }
        }
        $response->eventProject()->associate($event);
        $response->guest()->associate($guest);
        $response->save();

        return $response->fresh();
    }

    private function invitation(EventProject $event, Guest $guest, string $token): Invitation
    {
        $invitation = new Invitation(['token_hash' => hash('sha256', $token)]);
        $invitation->eventProject()->associate($event);
        $invitation->guest()->associate($guest);
        $invitation->save();

        return $invitation;
    }

    private function payload(EventProject $event): array
    {
        return ['event_project_id' => $event->id, 'submission_key' => Str::uuid()->toString(), 'full_name' => 'Telephone Guest', 'attendance_status' => 'attending', 'party_size' => 1, 'change_reason' => 'Recorded by telephone'];
    }

    private function editData(RsvpResponse $response): array
    {
        return ['full_name' => $response->guest->full_name, 'email' => $response->guest->email, 'attendance_status' => $response->attendance_status, 'party_size' => $response->party_size, 'revision' => $response->revision, 'change_reason' => 'Corrected by organizer'];
    }

    private function action(RsvpResponse $response): array
    {
        return ['revision' => $response->revision, 'change_reason' => 'Guest requested this change', 'confirm' => '1'];
    }
}

<?php

namespace Tests\Feature;

use App\Models\EventProject;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RsvpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_exposes_only_published_public_invitation_details(): void
    {
        $owner = User::factory()->create();
        $published = $this->event($owner, [
            'status' => 'published',
            'access_mode' => 'public',
        ]);
        $published->occasionLabels()->create(['label' => 'Christening', 'display_order' => 0]);
        $published->occasionLabels()->create(['label' => 'First Birthday', 'display_order' => 1]);
        $published->guests()->create([
            'full_name' => 'Private Guest',
            'email' => 'guest@example.test',
        ]);

        $this->getJson('/api/events/'.$published->public_slug)
            ->assertOk()
            ->assertJsonPath('event.combined_title', 'A combined celebration')
            ->assertJsonPath('event.occasion_labels.0', 'Christening')
            ->assertJsonPath('event.occasion_labels.1', 'First Birthday')
            ->assertJsonMissingPath('event.guests')
            ->assertJsonMissingPath('event.email');

        $this->getJson('/api/events/'.$this->event($owner)->public_slug)->assertNotFound();
        $this->getJson('/api/events/'.$this->event($owner, [
            'status' => 'published',
            'access_mode' => 'private',
        ])->public_slug)->assertNotFound();
    }

    public function test_public_rsvp_can_be_updated_using_its_random_browser_key(): void
    {
        $event = $this->event(User::factory()->create(), [
            'status' => 'published',
            'access_mode' => 'public',
        ]);
        $responseKey = fake()->uuid();

        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', $this->rsvpData($responseKey))
            ->assertCreated()
            ->assertJsonPath('message', 'Your RSVP has been saved.');

        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', $this->rsvpData($responseKey, [
            'full_name' => 'Updated Guest',
            'party_size' => 3,
        ]))->assertCreated();

        $this->assertSame(1, RsvpResponse::query()->where('event_project_id', $event->id)->count());
        $this->assertDatabaseHas('guests', [
            'event_project_id' => $event->id,
            'full_name' => 'Updated Guest',
        ]);
        $this->assertDatabaseHas('rsvp_responses', [
            'event_project_id' => $event->id,
            'attendance_status' => 'attending',
            'party_size' => 3,
        ]);
        $this->assertDatabaseMissing('rsvp_responses', ['public_response_key_hash' => $responseKey]);
    }

    public function test_private_invitation_allows_only_its_own_response_and_revocation(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, [
            'status' => 'published',
            'access_mode' => 'private',
        ]);
        $guest = $event->guests()->create([
            'full_name' => 'Private Guest',
            'email' => 'private@example.test',
        ]);
        $token = str()->random(64);
        $invitation = new Invitation([
            'token_hash' => hash('sha256', $token),
        ]);
        $invitation->eventProject()->associate($event);
        $invitation->guest()->associate($guest);
        $invitation->save();

        $this->getJson('/api/invitations/'.$token)
            ->assertOk()
            ->assertJsonPath('guest_name', 'Private Guest')
            ->assertJsonMissingPath('guest.email');

        $this->postJson('/api/invitations/'.$token.'/rsvp', $this->rsvpData(null, [
            'full_name' => 'Private Guest',
        ]))->assertCreated();

        $this->assertDatabaseHas('rsvp_responses', [
            'event_project_id' => $event->id,
            'invitation_id' => $invitation->id,
        ]);
        $this->assertSame(1, RsvpResponse::query()->where('event_project_id', $event->id)->count());

        $invitation->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->getJson('/api/invitations/'.$token)->assertNotFound();
        $this->postJson('/api/invitations/'.$token.'/rsvp', $this->rsvpData(null))->assertNotFound();
    }

    public function test_public_rsvp_enforces_deadline_and_party_size(): void
    {
        $event = $this->event(User::factory()->create(), [
            'status' => 'published',
            'access_mode' => 'public',
            'max_party_size' => 2,
        ]);

        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', $this->rsvpData(fake()->uuid(), [
            'party_size' => 3,
        ]))->assertUnprocessable()->assertJsonValidationErrors('party_size');

        $event->update(['rsvp_deadline' => now()->subMinute()]);
        $this->postJson('/api/events/'.$event->public_slug.'/rsvps', $this->rsvpData(fake()->uuid()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rsvp');
    }

    public function test_organizers_can_only_read_and_export_their_own_event_data(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $event = $this->event($owner);
        $guest = $event->guests()->create(['full_name' => '=1+1', 'email' => 'guest@example.test']);
        $response = new RsvpResponse([
            'attendance_status' => 'attending',
            'party_size' => 2,
            'additional_guest_names' => [],
            'submitted_at' => now(),
        ]);
        $response->eventProject()->associate($event);
        $response->guest()->associate($guest);
        $response->save();

        $this->actingAs($otherOwner)
            ->getJson('/api/admin/events/'.$event->id.'/responses')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/api/admin/events/'.$event->id.'/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee("'=1+1", false);
    }

    public function test_administration_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/events')->assertUnauthorized();
    }

    public function test_organizer_can_log_in_with_a_password_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'organizer@example.test',
            'password' => 'a-secure-password',
        ]);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'a-secure-password',
            ])->assertOk()->assertJsonPath('user.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_organizer_can_log_in_with_a_username(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => 'a-secure-password',
        ]);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'identifier' => 'admin',
                'password' => 'a-secure-password',
            ])->assertOk()->assertJsonPath('user.username', 'admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_organizer_can_create_multiple_projects_with_one_combined_invitation_each(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $firstPayload = $this->eventData('first-celebration', ['Christening', 'First Birthday']);

        $first = $this->postJson('/api/admin/events', $firstPayload)
            ->assertCreated()
            ->assertJsonCount(2, 'event.occasion_labels');
        $firstId = $first->json('event.id');

        $this->putJson('/api/admin/events/'.$firstId, array_merge($firstPayload, ['status' => 'published']))
            ->assertOk();
        $this->getJson('/api/events/first-celebration')
            ->assertOk()
            ->assertJsonCount(2, 'event.occasion_labels');

        $secondPayload = $this->eventData('second-celebration', ['Anniversary']);
        $this->postJson('/api/admin/events', $secondPayload)->assertCreated();
        $this->getJson('/api/admin/events')->assertOk()->assertJsonCount(2, 'events');
        $this->assertDatabaseCount('event_projects', 2);
        $this->assertDatabaseCount('occasion_labels', 3);
    }

    public function test_private_link_creation_returns_a_token_only_once_and_stores_its_hash(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['access_mode' => 'private']);

        $response = $this->actingAs($owner)
            ->postJson('/api/admin/events/'.$event->id.'/invitations', [
                'full_name' => 'Invited Guest',
                'email' => 'invited@example.test',
            ])
            ->assertCreated()
            ->assertJsonPath('invitation.guest.full_name', 'Invited Guest');

        $url = $response->json('invitation.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = $query['invite'];

        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('invitations', [
            'event_project_id' => $event->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
    }

    public function test_metrics_separate_rsvp_parties_people_and_pending_private_invites(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, [
            'status' => 'published',
            'access_mode' => 'private',
        ]);

        $awaitingGuest = $event->guests()->create(['full_name' => 'Awaiting Guest']);
        $awaitingInvitation = $this->invitation($event, $awaitingGuest);

        $attendingGuest = $event->guests()->create(['full_name' => 'Attending Guest']);
        $attendingInvitation = $this->invitation($event, $attendingGuest);
        $this->postJson('/api/invitations/'.$attendingInvitation['token'].'/rsvp', $this->rsvpData(null, [
            'full_name' => 'Attending Guest',
            'party_size' => 3,
        ]))->assertCreated();

        $declinedGuest = $event->guests()->create(['full_name' => 'Declined Guest']);
        $declinedInvitation = $this->invitation($event, $declinedGuest);
        $this->postJson('/api/invitations/'.$declinedInvitation['token'].'/rsvp', $this->rsvpData(null, [
            'full_name' => 'Declined Guest',
            'attendance_status' => 'declined',
            'party_size' => 0,
            'additional_guest_names' => [],
        ]))->assertCreated();

        $this->actingAs($owner)
            ->getJson('/api/admin/events/'.$event->id.'/metrics')
            ->assertOk()
            ->assertJsonPath('metrics.submissions', 2)
            ->assertJsonPath('metrics.attending_parties', 1)
            ->assertJsonPath('metrics.confirmed_attendees', 3)
            ->assertJsonPath('metrics.declined', 1)
            ->assertJsonPath('metrics.pending', 1);
    }

    public function test_duplicate_event_is_a_separate_draft_without_guest_data(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, ['status' => 'published']);
        $event->occasionLabels()->create(['label' => 'Christening', 'display_order' => 0]);
        $event->guests()->create(['full_name' => 'Original Guest']);

        $this->actingAs($owner)
            ->postJson('/api/admin/events/'.$event->id.'/duplicate')
            ->assertCreated()
            ->assertJsonPath('event.status', 'draft')
            ->assertJsonPath('event.occasion_labels.0.label', 'Christening')
            ->assertJsonPath('event.combined_title', 'A combined celebration (copy)');

        $this->assertSame(2, $owner->eventProjects()->count());
        $this->assertSame(1, $event->guests()->count());
        $this->assertSame(0, $owner->eventProjects()->where('status', 'draft')->firstOrFail()->guests()->count());
    }

    public function test_switching_private_event_to_public_revokes_existing_private_links(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner, [
            'access_mode' => 'private',
            'status' => 'published',
        ]);
        $guest = $event->guests()->create(['full_name' => 'Guest']);
        $invite = $this->invitation($event, $guest);

        $payload = $event->only([
            'internal_name',
            'public_slug',
            'combined_title',
            'event_date',
            'timezone',
            'theme',
            'access_mode',
            'allow_plus_ones',
            'max_party_size',
            'status',
        ]);
        $payload['access_mode'] = 'public';
        $payload['occasion_labels'] = [];

        $this->actingAs($owner)
            ->putJson('/api/admin/events/'.$event->id, $payload)
            ->assertOk();

        $this->assertDatabaseHas('invitations', ['id' => $invite['id'], 'status' => 'revoked']);
        $this->getJson('/api/invitations/'.$invite['token'])->assertNotFound();
    }

    private function event(User $owner, array $overrides = []): EventProject
    {
        return $owner->eventProjects()->create(array_merge([
            'internal_name' => 'Celebration project',
            'public_slug' => 'celebration-'.$owner->id.'-'.str()->lower(str()->random(6)),
            'combined_title' => 'A combined celebration',
            'event_date' => now()->addMonth()->toDateString(),
            'timezone' => 'UTC',
            'theme' => 'sage',
            'status' => 'draft',
            'access_mode' => 'public',
            'allow_plus_ones' => true,
            'max_party_size' => 4,
        ], $overrides));
    }

    private function invitation(EventProject $event, Guest $guest): array
    {
        $token = str()->random(64);
        $invitation = new Invitation(['token_hash' => hash('sha256', $token)]);
        $invitation->eventProject()->associate($event);
        $invitation->guest()->associate($guest);
        $invitation->save();

        return ['id' => $invitation->id, 'token' => $token];
    }

    private function eventData(string $slug, array $labels): array
    {
        return [
            'internal_name' => $slug,
            'public_slug' => $slug,
            'combined_title' => 'A combined celebration',
            'welcome_message' => 'We hope you can join us.',
            'event_date' => now()->addMonth()->toDateString(),
            'start_time' => '11:30',
            'end_time' => '15:00',
            'timezone' => 'UTC',
            'venue_name' => 'The Garden House',
            'venue_address' => 'Makati City',
            'map_url' => null,
            'rsvp_deadline' => null,
            'cover_image_url' => null,
            'gallery_urls' => [],
            'theme' => 'sage',
            'access_mode' => 'public',
            'allow_plus_ones' => true,
            'max_party_size' => 4,
            'occasion_labels' => $labels,
        ];
    }

    private function rsvpData(?string $responseKey, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Sample Guest',
            'attendance_status' => 'attending',
            'party_size' => 2,
            'email' => 'sample@example.test',
            'phone' => null,
            'additional_guest_names' => ['Additional Guest'],
            'dietary_or_accessibility_notes' => null,
            'message' => null,
        ], $responseKey === null ? [] : ['response_key' => $responseKey], $overrides);
    }
}

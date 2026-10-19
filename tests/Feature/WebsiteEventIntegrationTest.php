<?php

namespace Tests\Feature;

use App\Models\EventProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class WebsiteEventIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_endpoint_requires_explicit_public_published_event(): void
    {
        $event = $this->event();
        $this->getJson('/api/website-event')->assertNotFound();
        config(['app.public_event_slug' => $event->public_slug]);
        $this->getJson('/api/website-event')->assertOk()->assertJsonPath('event.id', $event->id);
        $event->update(['access_mode' => 'private']);
        $this->getJson('/api/website-event')->assertNotFound();
        $event->update(['access_mode' => 'public', 'status' => 'draft']);
        $this->getJson('/api/website-event')->assertNotFound();
    }

    public function test_blade_defaults_timezone_without_browser_input_and_preserves_existing_zone(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $data = ['internal_name' => 'QA', 'combined_title' => 'QA Birthday', 'event_date' => '2027-01-09', 'event_stops' => [['label' => 'Birthday', 'start_time' => '11:30', 'end_time' => '15:00']], 'rsvp_deadline_local' => '2027-01-08T18:30'];
        $this->actingAs($user)->get('/admin/event-settings/create')->assertOk()->assertDontSee('name="timezone"', false);
        $this->post('/admin/event-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $event = EventProject::firstOrFail();
        $this->assertSame('Asia/Manila', $event->timezone);
        $this->assertSame('2027-01-08 10:30', $event->rsvp_deadline->utc()->format('Y-m-d H:i'));
        $event->update(['timezone' => 'Asia/Tokyo']);
        $data['version'] = $event->fresh()->editVersion();
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Asia/Tokyo', $event->fresh()->timezone);
        $this->assertSame('2027-01-08 09:30', $event->fresh()->rsvp_deadline->utc()->format('Y-m-d H:i'));
    }

    public function test_nested_occasion_error_is_associated_with_the_field(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user)->from('/admin/event-settings/create')->post('/admin/event-settings', ['event_stops' => [['venue_name' => 'Church']]])->assertSessionHasErrors('event_stops.0.label');
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['event_stops.0.label' => ['An occasion name is required.']])));
        $this->blade('<x-admin.input name="event_stops[0][label]" id="occasion-0-label" label="Occasion name" />')->assertSee('aria-describedby="occasion-0-label-error"', false);
    }

    public function test_landing_title_saves_separately_is_exposed_to_public_api_and_can_be_cleared(): void
    {
        $this->withoutVite();
        $event = $this->event();
        $this->actingAs($event->owner)->get('/admin/event-settings/'.$event->id.'/edit')->assertOk()->assertSee('Landing page')->assertSee('Subtitle (optional)');
        $data = ['internal_name' => $event->internal_name, 'combined_title' => $event->combined_title, 'event_date' => '2027-01-09', 'landing_title' => 'Come celebrate with us', 'landing_subtitle' => 'A joyful day beneath the waves', 'version' => $event->fresh()->editVersion()];
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->getJson('/api/events/'.$event->public_slug)->assertOk()->assertJsonPath('event.landing_title', 'Come celebrate with us')->assertJsonPath('event.landing_subtitle', 'A joyful day beneath the waves')->assertJsonPath('event.combined_title', 'QA Ready Event');
        $data['landing_title'] = str_repeat('a', 161);
        $data['landing_subtitle'] = str_repeat('b', 301);
        $data['version'] = $event->fresh()->editVersion();
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasErrors(['landing_title', 'landing_subtitle']);
        $this->assertSame('Come celebrate with us', $event->fresh()->landing_title);
        $data['landing_title'] = '';
        $data['landing_subtitle'] = '';
        $this->put('/admin/event-settings/'.$event->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($event->fresh()->landing_title);
        $this->assertNull($event->fresh()->landing_subtitle);
    }

    public function test_delete_event_archives_and_preserves_related_records(): void
    {
        $this->withoutVite();
        $event = $this->event();
        $guest = $event->guests()->create(['full_name' => 'Existing Guest']);
        $this->actingAs($event->owner)->get('/admin/event-settings')->assertOk()->assertSee('#delete-event', false);
        $this->delete('/admin/event-settings/'.$event->id, ['version' => $event->fresh()->editVersion(), 'confirm' => 1, 'deletion_reason' => 'Event no longer needed'])->assertRedirect(route('admin.event-settings'));
        $this->assertSame('archived', $event->fresh()->status);
        $this->assertModelExists($guest);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'event.archived', 'subject_id' => $event->id]);
        $this->getJson('/api/events/'.$event->public_slug)->assertNotFound();
        $this->get('/admin/event-settings')->assertViewHas('events', fn ($rows) => $rows->total() === 0);
        $this->get('/admin/event-settings?status=archived')->assertViewHas('events', fn ($rows) => $rows->total() === 1);
    }

    public function test_delete_event_requires_authorization_confirmation_and_current_version(): void
    {
        $event = $this->event();
        $data = ['version' => $event->fresh()->editVersion(), 'confirm' => 1, 'deletion_reason' => 'Event no longer needed'];
        $this->actingAs(User::factory()->create())->delete('/admin/event-settings/'.$event->id, $data)->assertNotFound();
        $this->actingAs($event->owner)->delete('/admin/event-settings/'.$event->id, ['version' => $data['version']])->assertSessionHasErrors(['confirm', 'deletion_reason']);
        $event->update(['combined_title' => 'Changed title']);
        $this->delete('/admin/event-settings/'.$event->id, $data)->assertSessionHasErrors('version');
        $this->assertSame('published', $event->fresh()->status);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'event.archived']);
    }

    private function event(): EventProject
    {
        return User::factory()->create()->eventProjects()->create(['internal_name' => 'QA', 'combined_title' => 'QA Ready Event', 'public_slug' => 'qa-ready', 'event_date' => '2027-01-09', 'timezone' => 'Asia/Manila', 'status' => 'published', 'access_mode' => 'public', 'allow_plus_ones' => false, 'max_party_size' => 1]);
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\EventProject;
use App\Services\Events\EventRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->route('event') ? 'update' : 'create', $this->route('event') ?? EventProject::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $event = $this->route('event');
        $this->mergeIfMissing([
            'public_slug' => $event?->public_slug ?? 'event-'.Str::uuid()->toString(),
            'timezone' => $event?->timezone ?? config('app.event_timezone', 'Asia/Manila'),
            'status' => $event?->status ?? 'published',
            'access_mode' => $event?->access_mode ?? 'public',
            'allow_plus_ones' => $event?->allow_plus_ones ?? false,
            'max_party_size' => $event?->max_party_size ?? 1,
            'theme' => $event?->theme ?? 'sage',
            'change_reason' => 'Saved through Event Settings.',
        ]);

        if (is_array($this->input('event_stops'))) {
            $this->merge(['event_stops' => array_values(array_filter($this->input('event_stops'), fn ($stop): bool => ! is_array($stop) || count(array_filter($stop, fn ($value): bool => $value !== null && $value !== '')) > 0))]);
        }

        foreach (['occasion_labels_text' => 'occasion_labels', 'gallery_urls_text' => 'gallery_urls'] as $text => $array) {
            if ($this->has($text) && ($this->input($text) === null || is_string($this->input($text)))) {
                $this->merge([$array => array_values(array_filter(array_map('trim', explode("\n", (string) $this->input($text))), fn (string $line): bool => $line !== ''))]);
            }
        }
    }

    public function rules(EventRules $rules): array
    {
        $result = $rules->rules($this->route('event'));
        $result['event_date'] = ['required', 'date_format:Y-m-d'];
        $result['status'] = ['required', 'in:draft,published,archived'];
        $result['rsvp_deadline'] = ['prohibited'];
        $result['rsvp_deadline_local'] = ['nullable', 'date_format:Y-m-d\TH:i'];
        $result['version'] = [$this->isMethod('POST') ? 'prohibited' : 'required', 'string', 'size:64'];
        $result['change_reason'] = ['required', 'string', 'min:3', 'max:500'];
        $result['occasion_labels_text'] = ['nullable', 'string', 'max:1000'];
        $result['gallery_urls_text'] = ['nullable', 'string', 'max:25000'];
        $result['confirm_revoke'] = $this->route('event')?->access_mode === 'private' && $this->input('access_mode') === 'public' ? ['accepted'] : ['nullable', 'boolean'];

        return $result;
    }

    public function eventData(): array
    {
        $data = $this->validated();
        $deadline = $data['rsvp_deadline_local'] ?? null;
        if ($deadline) {
            $parsed = Carbon::createFromFormat('!Y-m-d\TH:i', $deadline, $data['timezone']);
            if ($parsed->format('Y-m-d\TH:i') !== $deadline) {
                throw ValidationException::withMessages(['rsvp_deadline_local' => ['This local time does not exist in the selected timezone.']]);
            }
            $data['rsvp_deadline'] = $parsed->utc()->toDateTimeString();
        } else {
            $data['rsvp_deadline'] = null;
        }

        return $data;
    }
}

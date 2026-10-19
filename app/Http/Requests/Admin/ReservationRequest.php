<?php

namespace App\Http\Requests\Admin;

use App\Models\RsvpResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        Gate::authorize($reservation ? 'update' : 'create', $reservation ?? RsvpResponse::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_guest_names_text') && ($this->input('additional_guest_names_text') === null || is_string($this->input('additional_guest_names_text')))) {
            $this->merge(['additional_guest_names' => array_values(array_filter(array_map('trim', explode("\n", (string) $this->input('additional_guest_names_text'))), fn (string $name): bool => $name !== ''))]);
        }
    }

    public function rules(): array
    {
        return [
            'event_project_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'submission_key' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'uuid'],
            'revision' => [$this->isMethod('POST') ? 'prohibited' : 'required', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'attendance_status' => ['required', 'in:attending,declined'],
            'party_size' => ['required', 'integer', 'min:0', 'max:'.($this->isMethod('POST') ? 1 : 20)],
            'dietary_or_accessibility_notes' => ['nullable', 'string', 'max:2000'], 'message' => ['nullable', 'string', 'max:2000'],
            'change_reason' => ['required', 'string', 'min:3', 'max:500'],
            'additional_guest_names_text' => ['nullable', 'string', 'max:2000'],
            'additional_guest_names' => ['nullable', 'array', 'max:19'], 'additional_guest_names.*' => ['string', 'max:100'],
        ];
    }
}

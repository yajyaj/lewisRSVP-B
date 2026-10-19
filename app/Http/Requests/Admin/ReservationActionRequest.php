<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReservationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->routeIs('admin.reservations.restore') ? 'restore' : 'delete', $this->route('reservation'));

        return true;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1'], 'change_reason' => ['required', 'string', 'min:3', 'max:500'], 'confirm' => ['accepted']];
    }
}

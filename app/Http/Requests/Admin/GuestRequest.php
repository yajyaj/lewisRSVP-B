<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('guest'));

        return true;
    }

    public function rules(): array
    {
        return ['full_name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'version' => ['required', 'string', 'size:64'], 'change_reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}

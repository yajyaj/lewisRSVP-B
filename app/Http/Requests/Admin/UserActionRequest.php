<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('user'));

        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'current_password:web'], 'version' => ['required', 'string', 'size:64'], 'change_reason' => ['required', 'string', 'min:3', 'max:500'], 'confirm' => ['accepted']];
    }
}

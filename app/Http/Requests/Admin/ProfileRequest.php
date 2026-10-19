<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('updateProfile', $this->user());

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id), Rule::unique('users', 'username')->ignore($this->user()->id)], 'current_password' => ['required', 'string', 'current_password:web'], 'version' => ['required', 'string', 'size:64'], 'role' => ['prohibited'], 'is_active' => ['prohibited'], 'user_id' => ['prohibited'], 'password' => ['prohibited'], 'username' => ['prohibited']];
    }
}

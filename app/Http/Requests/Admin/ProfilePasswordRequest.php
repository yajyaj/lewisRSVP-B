<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('updateProfile', $this->user());

        return true;
    }

    public function rules(): array
    {
        return ['password' => UserRequest::passwordRules(), 'current_password' => ['required', 'string', 'current_password:web'], 'version' => ['required', 'string', 'size:64']];
    }
}

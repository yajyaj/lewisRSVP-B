<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('resetPassword', $this->route('user'));

        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'current_password:web'], 'password' => UserRequest::passwordRules(), 'version' => ['required', 'string', 'size:64'], 'change_reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}

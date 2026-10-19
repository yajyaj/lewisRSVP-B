<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize($this->route('user') ? 'update' : 'create', $this->route('user') ?? User::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'), 'username' => is_string($this->input('username')) ? strtolower(trim($this->input('username'))) : $this->input('username')]);
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(), 'max:72', function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strlen($value) > 72) {
                $fail('Use a password of at most 72 bytes.');
            }
        }];
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id), Rule::unique('users', 'username')->ignore($id)],
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('users', 'username')->ignore($id), Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', 'in:staff,super_admin'], 'is_active' => ['required', 'boolean'],
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->isMethod('POST') ? self::passwordRules() : ['prohibited'],
            'version' => [$this->isMethod('POST') ? 'prohibited' : 'required', 'string', 'size:64'],
            'change_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}

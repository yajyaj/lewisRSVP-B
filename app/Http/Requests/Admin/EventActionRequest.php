<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EventActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('event'));

        return true;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'string', 'size:64'], 'confirm' => ['accepted'], 'deletion_reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\RsvpResponse;
use Illuminate\Foundation\Http\FormRequest;

class ReservationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RsvpResponse::class);
    }

    public function rules(): array
    {
        return [
            'event' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:attending,declined,cancelled'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_merge(['nullable', 'date_format:Y-m-d'], $this->filled('date_from') ? ['after_or_equal:date_from'] : []),
            'sort' => ['nullable', 'in:newest,oldest,name,party'], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

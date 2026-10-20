<?php

namespace Features\Subscription\Subscribe\Application;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->whereNull('deleted_at')],
            'use_trial' => ['sometimes', 'boolean'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}

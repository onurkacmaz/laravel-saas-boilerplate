<?php

namespace Features\Subscription\Subscribe\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'use_trial' => ['sometimes', 'boolean'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

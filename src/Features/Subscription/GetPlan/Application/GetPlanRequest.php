<?php

namespace Features\Subscription\GetPlan\Application;

use Illuminate\Foundation\Http\FormRequest;

class GetPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Define your validation rules here
        ];
    }
}

<?php

namespace Features\Audit\GetAudits\Application;

use Illuminate\Foundation\Http\FormRequest;

class GetAuditsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

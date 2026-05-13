<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('watch_project');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['nullable', 'string', 'max:64'],
            'sdk_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'timestamp' => ['nullable', 'date'],

            'events' => ['required', 'array', 'min:1', 'max:1000'],
            'events.*.type' => ['required', 'string', 'in:request,exception,query,job,event'],
            'events.*.id' => ['nullable', 'string', 'max:64'],
            'events.*.data' => ['required', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                return;
            }

            $from = $this->input('from');
            $to = $this->input('to');

            if (is_string($from) && is_string($to) && $to < $from) {
                $validator->errors()->add('to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
            }
        });
    }
}

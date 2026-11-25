<?php

namespace App\Http\Requests\Api;

class ReportAssistantRequest extends DashboardReportRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'question' => ['required', 'string', 'min:8', 'max:500'],
        ]);
    }
}

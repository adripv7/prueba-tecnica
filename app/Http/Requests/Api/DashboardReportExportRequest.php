<?php

namespace App\Http\Requests\Api;

class DashboardReportExportRequest extends DashboardReportRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermissionTo('reports.view')
            && $user->hasPermissionTo('reports.export');
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'section' => [
                'required',
                'string',
                'in:sales_by_month,top_products,seller_ranking,branch_category_matrix,gross_margin_by_category',
            ],
            'top_products_by' => ['required_if:section,top_products', 'nullable', 'in:amount,units'],
        ]);
    }
}

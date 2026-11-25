<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\LocalAiUnavailableException;
use App\Http\Requests\Api\DashboardReportRequest;
use App\Http\Requests\Api\DashboardReportExportRequest;
use App\Http\Requests\Api\ReportAssistantRequest;
use App\Services\DashboardReportService;
use App\Services\ReportAssistantService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function assistant(
        ReportAssistantRequest $request,
        ReportAssistantService $assistant,
        DashboardReportService $reports,
    ): JsonResponse {
        try {
            return response()->json([
                'data' => $assistant->answer($request->validated(), $reports),
            ]);
        } catch (LocalAiUnavailableException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], 503);
        }
    }

    public function dashboard(DashboardReportRequest $request, DashboardReportService $reports): JsonResponse
    {
        return response()->json([
            'data' => $reports->dashboard($request->validated()),
        ]);
    }

    public function export(
        DashboardReportExportRequest $request,
        DashboardReportService $reports,
    ): StreamedResponse {
        $filters = $request->validated();
        $dashboard = $reports->dashboard($filters);
        [$headers, $rows] = $this->exportTable(
            $dashboard,
            $filters['section'],
            $filters['top_products_by'] ?? 'amount',
        );
        $filename = sprintf(
            'ventaslite-%s-%s-%s.csv',
            $filters['section'],
            $dashboard['filters']['from'],
            $dashboard['filters']['to'],
        );

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ',', '"', '\\', "\r\n");

            foreach ($rows as $row) {
                fputcsv($output, $row, ',', '"', '\\', "\r\n");
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportTable(array $dashboard, string $section, string $topProductsBy): array
    {
        return match ($section) {
            'sales_by_month' => [
                ['Periodo', 'Mes', 'Ventas con IVA (MXN)', 'Operaciones'],
                array_map(fn (array $row): array => [
                    $row['period'], $row['label'], $row['sales_total'], $row['transactions'],
                ], $dashboard['sales_by_month']),
            ],
            'top_products' => [
                ['SKU', 'Producto', 'Unidades', 'Importe antes de IVA (MXN)'],
                array_map(fn (array $row): array => [
                    $row['sku'], $row['name'], $row['units'], $row['amount'],
                ], $dashboard['top_products']['by_'.$topProductsBy]),
            ],
            'seller_ranking' => [
                ['Vendedor', 'Sucursal', 'Ciudad', 'Operaciones', 'Ticket promedio (MXN)', 'Ventas con IVA (MXN)'],
                array_map(fn (array $row): array => [
                    $row['seller']['name'],
                    $row['branch']['name'],
                    $row['branch']['city'],
                    $row['transactions'],
                    $row['average_ticket'],
                    $row['sales_total'],
                ], $dashboard['seller_ranking']),
            ],
            'branch_category_matrix' => $this->branchCategoryTable($dashboard['branch_category_matrix']),
            'gross_margin_by_category' => [
                ['Categoría', 'Unidades', 'Ingresos antes de IVA (MXN)', 'Costo mercancía (MXN)', 'Utilidad bruta (MXN)', 'Margen (%)'],
                array_map(fn (array $row): array => [
                    $row['category']['name'],
                    $row['units'],
                    $row['revenue'],
                    $row['cost'],
                    $row['gross_profit'],
                    $row['margin_percent'],
                ], $dashboard['gross_margin_by_category']),
            ],
        };
    }

    private function branchCategoryTable(array $matrix): array
    {
        $categoryNames = array_column($matrix['categories'], 'name');
        $headers = array_merge(['Sucursal', 'Ciudad'], $categoryNames, ['Total sucursal (MXN)']);
        $rows = array_map(fn (array $branch): array => array_merge(
            [$branch['branch']['name'], $branch['branch']['city']],
            array_column($branch['categories'], 'amount'),
            [$branch['total']],
        ), $matrix['branches']);
        $rows[] = array_merge(
            ['Totales por categoría', ''],
            array_column($matrix['categories'], 'total'),
            [$matrix['total']],
        );

        return [$headers, $rows];
    }
}

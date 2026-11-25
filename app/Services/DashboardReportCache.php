<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardReportCache
{
    private const VERSION_KEY = 'reports:dashboard:cache-version';

    /**
     * Cachea los agregados por periodo y sucursal. El versionado permite invalidar
     * todos los filtros sin depender de tags, que no están disponibles en todos los stores.
     */
    public function remember(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        Closure $callback,
    ): array {
        try {
            $version = Cache::get(self::VERSION_KEY, 1);
            $filters = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
            ];
            $key = 'reports:dashboard:v'.$version.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
            if (Cache::has($key)) {
                return Cache::get($key);
            }
        } catch (Throwable $exception) {
            Log::warning('No fue posible leer o guardar la caché del dashboard; se calculará el reporte directamente.', [
                'exception' => $exception::class,
            ]);

            return $callback();
        }

        // Mantener el cálculo fuera del try: un error real de consultas debe propagarse,
        // no tratarse como una falla del store ni ejecutarse una segunda vez.
        $report = $callback();

        try {
            Cache::put(
                $key,
                $report,
                now()->addSeconds((int) config('reports.cache_ttl_seconds', 60)),
            );
        } catch (Throwable $exception) {
            Log::warning('No fue posible guardar la caché del dashboard; el reporte ya calculado se devolverá normalmente.', [
                'exception' => $exception::class,
            ]);
        }

        return $report;
    }

    public function invalidate(): void
    {
        try {
            Cache::add(self::VERSION_KEY, 1, now()->addYears(10));
            Cache::increment(self::VERSION_KEY);
        } catch (Throwable $exception) {
            Log::warning('No fue posible invalidar la caché del dashboard; los resultados expirarán por TTL.', [
                'exception' => $exception::class,
            ]);
        }
    }
}

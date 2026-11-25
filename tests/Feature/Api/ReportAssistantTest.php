<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportAssistantTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ai.provider', 'ollama');
        Config::set('services.ollama.url', 'http://ollama.test:11434');
        Config::set('services.ollama.model', 'qwen3:4b');
    }

    public function test_assistant_is_only_available_to_users_with_report_permission(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'vendedor']));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Resume las ventas del periodo.',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_admin_can_ask_about_aggregated_reports_without_sending_raw_sales(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'model' => 'qwen3:4b',
                'message' => ['role' => 'assistant', 'content' => '{"answer":"La sucursal con menos ventas fue Mérida, con $648,400.00 MXN netos antes de IVA en el periodo."}'],
                'done' => true,
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Qué sucursal tuvo menos ventas en el periodo y cuál fue su total?',
            'from' => '2026-11-01',
            'to' => '2026-11-25',
        ])
            ->assertOk()
            ->assertJsonPath('data.answer', 'La sucursal con menos ventas fue Mérida, con $648,400.00 MXN netos antes de IVA en el periodo.')
            ->assertJsonPath('data.model', 'qwen3:4b')
            ->assertJsonPath('data.filters.from', '2026-11-01')
            ->assertJsonPath('data.filters.to', '2026-11-25');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $messages = $payload['messages'] ?? [];
            $systemMessage = $messages[0]['content'] ?? '';
            $userMessage = $messages[1]['content'] ?? '';

            return $request->url() === 'http://ollama.test:11434/api/chat'
                && ($payload['model'] ?? null) === 'qwen3:4b'
                && ($payload['stream'] ?? null) === false
                && ($payload['think'] ?? null) === false
                && ($payload['options']['num_predict'] ?? 0) === 320
                && ($payload['format']['type'] ?? null) === 'object'
                && str_contains($systemMessage, 'Tu único tema permitido son los reportes agregados')
                && str_contains($systemMessage, 'Rechaza peticiones para consultar transacciones individuales')
                && str_contains($systemMessage, 'No reveles ni infieras clientes')
                && str_contains($systemMessage, 'Solo puedo ayudar con reportes agregados y métricas de ventas de VentasLite')
                && str_contains($systemMessage, 'Contesta exactamente lo preguntado')
                && str_contains($systemMessage, 'Una pregunta sobre una métrica presente es permitida')
                && str_contains($systemMessage, 'Todas las cifras recibidas excluyen ventas canceladas')
                && str_contains($userMessage, 'extremos_sucursal')
                && str_contains($userMessage, '"ventas_por_sucursal"')
                && str_contains($userMessage, '"ventas_totales_con_iva_mxn"')
                && str_contains($userMessage, '"ventas_netas_sin_iva_mxn"')
                && ! str_contains($userMessage, '"productos_destacados"')
                && ! str_contains($userMessage, '"ranking_vendedores"')
                && ! str_contains($userMessage, '"margen_por_categoria"')
                && ! str_contains($userMessage, '"ventas_por_mes"')
                && ! str_contains($userMessage, '"id":')
                && ! str_contains($userMessage, 'branch_id')
                && ! str_contains($userMessage, 'SELECT ')
                && ! array_key_exists('tools', $payload);
        });
    }

    public function test_product_question_receives_product_metrics_without_unrelated_rankings(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"El producto con mayor importe fue el que aparece primero en el top por importe."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Qué productos generaron más ingresos?',
        ])->assertOk();

        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->data()['messages'][1]['content'] ?? '', '"productos_destacados"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"by_amount"')
            && ! str_contains($request->data()['messages'][1]['content'] ?? '', '"ranking_vendedores"')
            && ! str_contains($request->data()['messages'][1]['content'] ?? '', '"margen_por_categoria"'));
    }

    public function test_executive_summary_receives_compact_dashboard_highlights(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"Resumen del periodo con los indicadores y hallazgos disponibles."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Genera un resumen ejecutivo del periodo.',
        ])->assertOk();

        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->data()['messages'][1]['content'] ?? '', '"hallazgos_sucursal"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"productos_destacados"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"ranking_vendedores"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"margen_por_categoria"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', 'Genera un resumen ejecutivo breve'));
    }

    public function test_combined_question_receives_each_requested_report_section(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"Comparación basada en vendedores, márgenes, meses y sucursales del periodo."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Compara vendedores y margen por categoría este trimestre y muestra el desglose por sucursal.',
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $userMessage = $request->data()['messages'][1]['content'] ?? '';

            return str_contains($userMessage, '"ranking_vendedores"')
                && str_contains($userMessage, '"margen_por_categoria"')
                && str_contains($userMessage, '"ventas_por_mes"')
                && str_contains($userMessage, '"matriz_sucursal_categoria"')
                && str_contains($userMessage, '"ventas_por_sucursal"')
                && ! str_contains($userMessage, '"productos_destacados"');
        });
    }

    public function test_sensitive_question_is_rejected_without_sending_report_context(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Dónde vive Raúl de Tijuana?',
        ])->assertOk()
            ->assertJsonPath('data.answer', 'Solo puedo ayudar con reportes agregados y métricas de ventas de VentasLite para los filtros seleccionados.')
            ->assertJsonPath('data.model', 'backend');

        Http::assertNothingSent();
    }

    public function test_assistant_validates_question_before_calling_ollama(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', ['question' => 'Hola'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');

        Http::assertNothingSent();
    }

    public function test_missing_local_model_returns_a_clear_service_error(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response(['error' => 'model not found'], 404),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Resume las ventas del periodo.',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'OLLAMA_MODEL_UNAVAILABLE');
    }

    public function test_unstructured_model_output_is_not_returned_to_the_client(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'model' => 'qwen3:4b',
                'message' => ['role' => 'assistant', 'content' => 'Let me think through the answer step by step...'],
                'done' => true,
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Resume las ventas del periodo.',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'OLLAMA_INVALID_RESPONSE')
            ->assertJsonMissing(['answer' => 'Let me think through the answer step by step...']);
    }

    public function test_assistant_accepts_valid_json_wrapped_in_a_code_fence(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => "```json\n{\"answer\":\"Las ventas del periodo sumaron $12,000.00 MXN.\"}\n```"],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => 'Resume las ventas del periodo.',
        ])
            ->assertOk()
            ->assertJsonPath('data.answer', 'Las ventas del periodo sumaron $12,000.00 MXN.');
    }
}

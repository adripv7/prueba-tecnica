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
                && ($payload['options']['num_ctx'] ?? 0) === 8192
                && ($payload['options']['num_predict'] ?? 0) === 320
                && ($payload['format']['type'] ?? null) === 'object'
                && str_contains($systemMessage, 'El único tema es el análisis agregado de ventas de VentasLite')
                && str_contains($systemMessage, 'Rechaza temas ajenos al MVP y solicitudes de clientes')
                && str_contains($systemMessage, 'No reveles ni infieras domicilio')
                && str_contains($systemMessage, 'Tú decides qué sección responde la pregunta')
                && str_contains($systemMessage, 'máximo cuatro frases más los elementos de una lista solicitada')
                && str_contains($userMessage, '"matriz_sucursal_categoria_neta_sin_iva_mxn"')
                && str_contains($userMessage, '"productos_mas_vendidos_por_unidades_top_10"')
                && str_contains($userMessage, '"ranking_vendedores"')
                && str_contains($userMessage, '"margen_bruto_por_categoria"')
                && str_contains($userMessage, '"ventas_por_mes"')
                && ! str_contains($userMessage, '"id":')
                && ! str_contains($userMessage, 'branch_id')
                && ! str_contains($userMessage, 'customer_name')
                && ! str_contains($userMessage, '"folio"')
                && ! str_contains($userMessage, 'SELECT ')
                && ! array_key_exists('tools', $payload);
        });
    }

    public function test_product_question_is_sent_with_all_compact_report_sections_for_model_reasoning(): void
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
            str_contains($request->data()['messages'][1]['content'] ?? '', '"productos_mas_vendidos_por_unidades_top_10"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"productos_mas_vendidos_por_importe_top_10"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"ranking_vendedores"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"margen_bruto_por_categoria"')
            && str_contains($request->data()['messages'][0]['content'] ?? '', 'Responde solo ese tema')
            && str_contains($request->data()['messages'][0]['content'] ?? '', 'Compáralos con `filtros.desde`'));
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
            str_contains($request->data()['messages'][1]['content'] ?? '', '"matriz_sucursal_categoria_neta_sin_iva_mxn"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"productos_mas_vendidos_por_unidades_top_10"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"ranking_vendedores"')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"margen_bruto_por_categoria"')
            && str_contains($request->data()['messages'][0]['content'] ?? '', 'Tú decides qué sección responde la pregunta'));
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
                && str_contains($userMessage, '"margen_bruto_por_categoria"')
                && str_contains($userMessage, '"ventas_por_mes"')
                && str_contains($userMessage, '"matriz_sucursal_categoria_neta_sin_iva_mxn"')
            && str_contains($userMessage, '"productos_mas_vendidos_por_unidades_top_10"');
        });
    }

    public function test_empty_period_is_sent_to_model_instead_of_using_a_fixed_answer(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"No hay ventas pagadas en ese periodo."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Qué pasó con las ventas en ese periodo?',
            'from' => '2037-12-01',
            'to' => '2037-12-31',
        ])->assertOk()->assertJsonPath('data.model', 'qwen3:4b');

        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->data()['messages'][1]['content'] ?? '', '"transactions":0')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"ventas_canceladas":"excluidas"'));
    }

    public function test_question_with_a_different_month_is_sent_to_model_with_active_filters(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"Ajusta los filtros para consultar diciembre de 2024."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Cuáles fueron los productos más vendidos de diciembre de 2024?',
            'from' => '2026-08-25',
            'to' => '2026-11-25',
        ])->assertOk()->assertJsonPath('data.model', 'qwen3:4b');

        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->data()['messages'][1]['content'] ?? '', 'diciembre de 2024')
            && str_contains($request->data()['messages'][1]['content'] ?? '', '"desde":"2026-08-25"')
            && str_contains($request->data()['messages'][0]['content'] ?? '', 'Responde solo ese tema')
            && str_contains($request->data()['messages'][0]['content'] ?? '', 'no contestes con métricas de otro periodo'));
    }

    public function test_seller_threshold_question_is_sent_to_model_with_aggregated_ranking(): void
    {
        Http::fake([
            'http://ollama.test:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '{"answer":"No hay vendedores que cumplan el umbral."}'],
            ]),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'branch_id' => null]));

        $this->postJson('/api/reports/assistant', [
            'question' => '¿Qué vendedores tienen al menos $5,000 pesos en ventas?',
            'from' => '2037-12-01',
            'to' => '2037-12-31',
        ])->assertOk()->assertJsonPath('data.model', 'qwen3:4b');

        Http::assertSent(fn (Request $request): bool =>
            str_contains($request->data()['messages'][1]['content'] ?? '', '"ranking_vendedores":[]')
            && str_contains($request->data()['messages'][1]['content'] ?? '', 'al menos $5,000 pesos'));
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

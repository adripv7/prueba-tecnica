<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_PERMISSIONS = [
        ['slug' => 'catalog.view', 'name' => 'Consultar catálogo'],
        ['slug' => 'sales.create', 'name' => 'Registrar ventas'],
        ['slug' => 'sales.create_any_branch', 'name' => 'Vender en cualquier sucursal'],
        ['slug' => 'sales.view_all', 'name' => 'Consultar todas las ventas'],
        ['slug' => 'sales.cancel', 'name' => 'Cancelar ventas'],
        ['slug' => 'reports.view', 'name' => 'Consultar reportes'],
        ['slug' => 'reports.export', 'name' => 'Exportar reportes'],
        ['slug' => 'inventory.view_all_branches', 'name' => 'Consultar inventario de cualquier sucursal'],
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        $now = now();
        $roleSlugs = array_unique(array_merge(
            ['admin', 'vendedor'],
            DB::table('users')->distinct()->pluck('role')->filter()->values()->all(),
        ));

        foreach ($roleSlugs as $slug) {
            DB::table('roles')->insertOrIgnore([
                'slug' => $slug,
                'name' => $slug === 'admin' ? 'Administrador' : ($slug === 'vendedor' ? 'Vendedor' : ucfirst($slug)),
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('permissions')->insert(array_map(fn (array $permission): array => [
            ...$permission,
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::DEFAULT_PERMISSIONS));

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $assignments = [];

        foreach ($permissionIds as $slug => $permissionId) {
            $assignments[] = ['role_id' => $roleIds['admin'], 'permission_id' => $permissionId];

            if (in_array($slug, ['catalog.view', 'sales.create'], true)) {
                $assignments[] = ['role_id' => $roleIds['vendedor'], 'permission_id' => $permissionId];
            }
        }

        DB::table('permission_role')->insert($assignments);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role', 'users_role_foreign')
                ->references('slug')
                ->on('roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_role_foreign');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

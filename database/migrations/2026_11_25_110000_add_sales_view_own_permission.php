<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissionId = DB::table('permissions')->where('slug', 'sales.view_own')->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'slug' => 'sales.view_own',
                'name' => 'Consultar ventas propias',
                'description' => 'Consultar el historial y detalle de las ventas propias.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'vendedor'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'sales.view_own')->value('id');
        if ($permissionId === null) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'vendedor'])->pluck('id');
        DB::table('permission_role')
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permissionId)
            ->delete();

        if (! DB::table('permission_role')->where('permission_id', $permissionId)->exists()) {
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};

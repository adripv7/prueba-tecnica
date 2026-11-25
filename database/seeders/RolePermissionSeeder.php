<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'catalog.view' => ['Consultar catálogo', 'Consultar sucursales, categorías y productos permitidos.'],
        'sales.create' => ['Registrar ventas', 'Registrar ventas en la sucursal asignada.'],
        'sales.create_any_branch' => ['Vender en cualquier sucursal', 'Registrar ventas en cualquier sucursal.'],
        'sales.view_own' => ['Consultar ventas propias', 'Consultar el historial y detalle de las ventas propias.'],
        'sales.view_all' => ['Consultar todas las ventas', 'Consultar historial y detalle de ventas.'],
        'sales.cancel' => ['Cancelar ventas', 'Cancelar ventas y devolver existencias.'],
        'reports.view' => ['Consultar reportes', 'Consultar el dashboard y sus agregados.'],
        'reports.export' => ['Exportar reportes', 'Descargar reportes en CSV.'],
        'inventory.view_all_branches' => ['Consultar inventario de cualquier sucursal', 'Consultar productos y existencias de cualquier sucursal.'],
    ];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $slug => [$name, $description]) {
            $permissions[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
        }

        $admin = Role::query()->updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrador', 'description' => 'Acceso administrativo a los módulos del sistema.'],
        );
        $seller = Role::query()->updateOrCreate(
            ['slug' => 'vendedor'],
            ['name' => 'Vendedor', 'description' => 'Acceso al catálogo y registro de ventas de su sucursal.'],
        );

        $admin->permissions()->sync(array_map(fn (Permission $permission): int => $permission->id, array_values($permissions)));
        $seller->permissions()->sync([
            $permissions['catalog.view']->id,
            $permissions['sales.create']->id,
            $permissions['sales.view_own']->id,
        ]);
    }
}

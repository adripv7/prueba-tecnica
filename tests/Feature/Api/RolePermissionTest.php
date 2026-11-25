<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_role_has_all_permissions_and_seller_has_catalog_sales_creation_and_own_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);
        $seller = User::factory()->create(['role' => 'vendedor']);

        $this->assertEqualsCanonicalizing([
            'catalog.view',
            'inventory.view_all_branches',
            'reports.export',
            'reports.view',
            'sales.cancel',
            'sales.create',
            'sales.create_any_branch',
            'sales.view_own',
            'sales.view_all',
        ], $admin->permissionSlugs());
        $this->assertTrue($admin->roleDefinition->permissions->contains('slug', 'reports.view'));
        $this->assertTrue($admin->hasPermissionTo('reports.export'));
        $this->assertTrue($admin->hasPermissionTo('sales.cancel'));

        $this->assertTrue($seller->hasPermissionTo('catalog.view'));
        $this->assertTrue($seller->hasPermissionTo('sales.create'));
        $this->assertTrue($seller->hasPermissionTo('sales.view_own'));
        $this->assertFalse($seller->hasPermissionTo('sales.view_all'));
        $this->assertFalse($seller->hasPermissionTo('reports.view'));
        $this->assertFalse($seller->hasPermissionTo('inventory.view_all_branches'));
        $this->assertEqualsCanonicalizing(['catalog.view', 'sales.create', 'sales.view_own'], $seller->permissionSlugs());
    }

    public function test_authenticated_user_response_includes_permission_slugs(): void
    {
        $seller = User::factory()->create(['role' => 'vendedor']);
        $token = $seller->createToken('role-permission-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.role', 'vendedor')
            ->assertJsonPath('user.permissions.0', 'catalog.view');
    }
}

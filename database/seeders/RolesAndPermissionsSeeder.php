<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Usuarios
            'user.view',
            'user.create',
            'user.update',
            'user.delete',

            // Roles
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permisos
            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',

            // Bodegas
            'warehouse.view',
            'warehouse.create',
            'warehouse.update',
            'warehouse.delete',
            'warehouse.restore',

            // Categorías
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
            'category.restore',

            // Unidades
            'unit.view',
            'unit.create',
            'unit.update',
            'unit.delete',
            'unit.restore',

            // Personas
            'person.view',
            'person.create',
            'person.update',
            'person.delete',
            'person.restore',

            // Materiales
            'material.view',
            'material.create',
            'material.update',
            'material.delete',
            'material.restore',

            // Existencias por bodega
            'warehouse_stock.view',
            'warehouse_stock.create',
            'warehouse_stock.update',
            'warehouse_stock.delete',

            // Movimientos
            'inventory_movement.view',
            'inventory_movement.entry',
            'inventory_movement.consumption',
            'inventory_movement.adjustment',

            // Préstamos
            'loan.view',
            'loan.create',
            'loan.return',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'Super Administrador',
            'Administrador',
            'Encargado de Inventario',
            'Operador',
            'Consulta',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        Role::findByName('Super Administrador')
            ->syncPermissions($permissions);

        Role::findByName('Administrador')
            ->syncPermissions($permissions);

        Role::findByName('Encargado de Inventario')
            ->syncPermissions([
                'warehouse.view',
                'warehouse.create',
                'warehouse.update',
                'warehouse.delete',
                'warehouse.restore',
                'category.view',
                'category.create',
                'category.update',
                'category.delete',
                'category.restore',

                'unit.view',
                'unit.create',
                'unit.update',
                'unit.delete',
                'unit.restore',

                'person.view',
                'person.create',
                'person.update',
                'person.delete',
                'person.restore',

                // Materiales
                'material.view',
                'material.create',
                'material.update',
                'material.delete',
                'material.restore',

                // Existencias por bodega
                'warehouse_stock.view',
                'warehouse_stock.create',
                'warehouse_stock.update',
                'warehouse_stock.delete',

                // Movimientos
                'inventory_movement.view',
                'inventory_movement.entry',
                'inventory_movement.consumption',
                'inventory_movement.adjustment',

                'loan.view',
                'loan.return',
            ]);

        Role::findByName('Operador')
            ->syncPermissions([
                'warehouse.view',
                'category.view',
                'unit.view',
                'person.view',
                'person.create',
                'person.update',
                'material.view',
                'material.create',
                'material.update',
                'warehouse_stock.view',
                'inventory_movement.view',
                'inventory_movement.entry',
                'inventory_movement.consumption',
                'loan.view',
                'loan.return',
            ]);

        Role::findByName('Consulta')
            ->syncPermissions([
                'warehouse.view',
                'category.view',
                'unit.view',
                'person.view',
                'material.view',
                'warehouse_stock.view',
                'inventory_movement.view',
                'loan.view',
            ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
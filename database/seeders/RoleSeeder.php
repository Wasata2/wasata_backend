<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate — safe to run even though customer/broker/admin already
        // exist (added manually via tinker earlier). Won't throw a duplicate-entry
        // error on the unique role_name column, and fills in the description
        // for rows that don't have one yet.
        $roles = [
            ['role_name' => 'customer', 'description' => 'End-user requesting SHEIN services'],
            ['role_name' => 'broker',   'description' => 'SHEIN intermediary / service provider (وسيطة Shein)'],
            ['role_name' => 'admin',    'description' => 'Platform administrator'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['role_name' => $role['role_name']],
                ['description' => $role['description']]
            );
        }
    }
}

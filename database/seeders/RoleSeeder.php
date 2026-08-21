<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['role_name' => 'customer', 'description' => 'End-user requesting SHEIN ordering/brokerage services', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'broker',   'description' => 'SHEIN intermediary / service provider (وسيطة Shein)', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'admin',    'description' => 'Platform administrator', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

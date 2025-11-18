<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;
use App\Models\UserType;

class CatalogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        UserType::create([
            'name' => 'Superadmin',
            'code' => 'superadmin',
            'description' => 'Permiso a todo'
        ]);

        UserType::create([
            'name' => 'Enatrel',
            'code' => 'main_provider',
            'description' => 'Proveedor principal de servicios'
        ]);

        UserType::create([
            'name' => 'Representante de ISP',
            'code' => 'isp_representative',
            'description' => 'Cliente del proveedor principal de servicios'
        ]);

    }
}

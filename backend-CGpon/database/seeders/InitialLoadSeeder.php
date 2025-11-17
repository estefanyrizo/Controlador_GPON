<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ISP;
use App\Models\User;

class InitialLoadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Create ISPs
        ISP::create([
            'name' => 'Itel'
        ]);

        User::create([
            'username' => 'enriquem',
            'email' => 'enriquem@cootel.com.ni',
            'name' => 'Enrique Muñoz',
            'password' => '11111111',
            'user_type_id' => 1
        ]);

        User::create([
            'username' => 'usuarioitel',
            'email' => 'desarrollo@itel.com.ni',
            'name' => 'Itel',
            'password' => '11111111',
            'user_type_id' => 3,
            'isp_id' => 1
        ]);
    }
}

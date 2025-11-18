<?php

namespace Database\Seeders;

use App\Models\OLT;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OltSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $olt_base_config = [
            'port' => 23,
            'username' => 'gpon_itel',
            'password' => 'itel2025..',
            'must_login' => 'yes',
            'status' => true,
            'created_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $olts = [
            ['name' => 'OLT-ACHUAPA', 'ip_olt' => '172.27.3.24'],
            ['name' => 'OLT-SE ACOYAPA', 'ip_olt' => '172.20.99.10'],
            ['name' => 'OLT-SE BLUEFIELDS', 'ip_olt' => '172.20.172.10'],
            ['name' => 'OLT-BOACO PUEBLO', 'ip_olt' => '172.27.3.12'],
            ['name' => 'OLT-CAMOAPA', 'ip_olt' => '172.27.3.18'],
            ['name' => 'OLT-SE CATARINA', 'ip_olt' => '172.20.31.10'],
            ['name' => 'OLT-SE CHICHIGALPA', 'ip_olt' => '172.20.125.10'],
            ['name' => 'OLT-SE CHINANDEGA', 'ip_olt' => '172.20.126.10'],
            ['name' => 'OLT CINCO PINOS', 'ip_olt' => '172.27.3.4'],
            ['name' => 'OLT-CUAPA', 'ip_olt' => '172.27.3.20'],
            ['name' => 'OLT-COMALAPA', 'ip_olt' => '172.27.3.19'],
            ['name' => 'OLT-CONDEGA', 'ip_olt' => '172.20.41.10'],
            ['name' => 'OLT-SE CORINTO', 'ip_olt' => '172.20.126.12'],
            ['name' => 'OLT- SE DIRIAMBA', 'ip_olt' => '172.20.80.10'],
            ['name' => 'OLT- SE EL SAUCE', 'ip_olt' => '172.20.20.10'],
            ['name' => 'OLT-SE EL TUMA LA DALIA', 'ip_olt' => '172.27.3.27'],
            ['name' => 'OLT-ESQUIPULAS', 'ip_olt' => '172.27.3.11'],
            ['name' => 'OLT- SE ESTELI', 'ip_olt' => '172.20.50.10'],
            ['name' => 'OLT- SE LA GATEADA', 'ip_olt' => '172.20.100.10'],
            ['name' => 'OLT- SE GRANADA', 'ip_olt' => '172.20.75.10'],
            ['name' => 'OLT-JALAPA', 'ip_olt' => '172.20.87.10'],
            ['name' => 'OLT-SE JINOTEGA', 'ip_olt' => '172.20.135.10'],
            ['name' => 'OLT-SE LA ESPERANZA', 'ip_olt' => '172.27.3.34'],
            ['name' => 'OLT-SE LA PAZ CENTRO', 'ip_olt' => '172.20.16.11'],
            ['name' => 'OLT-SAN JOSE DE CUSMAPA', 'ip_olt' => '172.27.3.23'],
            ['name' => 'OLT-SE LEON', 'ip_olt' => '172.20.18.10'],
            ['name' => 'OLT-SE LOS BRASILES', 'ip_olt' => '172.20.5.10'],
            ['name' => 'OLT-SE MALPAISILLO', 'ip_olt' => '172.20.19.10'],
            ['name' => 'OLT-SE MASATEPE', 'ip_olt' => '172.20.32.10'],
            ['name' => 'OLT-SE MASAYA', 'ip_olt' => '172.20.30.10'],
            ['name' => 'OLT-SE MATAGALPAII', 'ip_olt' => '172.20.150.10'],
            ['name' => 'OLT-SE MATIGUAS', 'ip_olt' => '172.27.3.26'],
            ['name' => 'OLT MUY MUY', 'ip_olt' => '172.27.3.25'],
            ['name' => 'OLT-SE NAGAROTE', 'ip_olt' => '172.20.16.10'],
            ['name' => 'OLT-SE NANDAIME', 'ip_olt' => '172.27.3.14'],
            ['name' => 'OLT-SE OCOTAL', 'ip_olt' => '172.20.85.10'],
            ['name' => 'OLT OFC', 'ip_olt' => '172.20.0.10'],
            ['name' => 'OLT-PALACAGUINA', 'ip_olt' => '172.20.46.10'],
            ['name' => 'OLT-QUILALI', 'ip_olt' => '172.20.46.13'],
            ['name' => 'OLT RANCHO GRANDE', 'ip_olt' => '172.27.3.30'],
            ['name' => 'OLT-SE RIVAS', 'ip_olt' => '172.27.3.15'],
            ['name' => 'OLT-SE BOACO', 'ip_olt' => '172.27.3.13'],
            ['name' => 'OLT-SE ORIENTAL', 'ip_olt' => '172.20.2.10'],
            ['name' => 'OLT-SE SAN BENITO', 'ip_olt' => '172.20.3.10'],
            ['name' => 'OLT-SAN DIONISIO', 'ip_olt' => '172.27.3.10'],
            ['name' => 'OLT-SAN FRANCISCO DEL NORTE', 'ip_olt' => '172.27.3.5'],
            ['name' => 'OLT-SAN FRANCISCO LIBRE', 'ip_olt' => '172.20.8.11'],
            ['name' => 'OLT-LA SABANA', 'ip_olt' => '172.27.3.22'],
            ['name' => 'OLT-EL CUA', 'ip_olt' => '172.27.3.29'],
            ['name' => 'OLT SAN JUAN DE RIO COCO', 'ip_olt' => '172.20.46.12'],
            ['name' => 'OLT-SAN LORENZO', 'ip_olt' => '172.27.3.16'],
            ['name' => 'OLT-SAN NICOLAS', 'ip_olt' => '172.27.3.8'],
            ['name' => 'OLT SAN RAFAEL DEL SUR', 'ip_olt' => '172.20.12.10'],
            ['name' => 'OLT-SE SAN RAMON', 'ip_olt' => '172.27.3.9'],
            ['name' => 'OLT-SE SANTA MARIA', 'ip_olt' => '172.20.98.10'],
            ['name' => 'OLT SANTO TOMAS CHONTALES', 'ip_olt' => '172.27.3.32'],
            ['name' => 'OLT-SE SEBACO', 'ip_olt' => '172.20.147.10'],
            ['name' => 'OLT-SOMOTILLO', 'ip_olt' => '172.27.3.3'],
            ['name' => 'OLT-SOMOTO', 'ip_olt' => '172.27.3.21'],
            ['name' => 'OLT-TECOLOSTOTE', 'ip_olt' => '172.27.3.17'],
            ['name' => 'OLT-TELPANECA', 'ip_olt' => '172.20.46.11'],
            ['name' => 'OLT-SE TICUANTEPE', 'ip_olt' => '172.20.14.10'],
            ['name' => 'OLT-SE TIPITAPA', 'ip_olt' => '172.20.10.10'],
            ['name' => 'OLT-TISMA', 'ip_olt' => '172.20.30.11'],
            ['name' => 'OLT-TOLA', 'ip_olt' => '172.20.61.10'],
            ['name' => 'OLT-TONALA', 'ip_olt' => '172.20.126.11'],
            ['name' => 'OLT-LA TRINIDAD', 'ip_olt' => '172.27.3.7'],
            ['name' => 'OLT-VILLA EL CARMEN', 'ip_olt' => '172.20.11.10'],
            ['name' => 'OLT-SE VILLA NUEVA', 'ip_olt' => '172.20.127.10'],
            ['name' => 'OLT-SE YALAGUINA', 'ip_olt' => '172.20.40.10'],
            ['name' => 'OLT-SE PERIODISTA', 'ip_olt' => '172.27.3.2'],
            ['name' => 'OLT-SE CIUDAD DARIO', 'ip_olt' => '172.20.147.11'],
            ['name' => 'OLT SAN RAFAEL DEL NORTE', 'ip_olt' => '172.20.136.10'],
            ['name' => 'OLT-EL CORAL', 'ip_olt' => '172.20.100.11'],
            ['name' => 'OLT SAN JUAN DE LIMAY', 'ip_olt' => '172.27.3.6'],
            ['name' => 'OLT-EL JICARAL', 'ip_olt' => '172.20.20.13'],
            ['name' => 'OLT-RIO BLANCO', 'ip_olt' => '172.20.154.11'],
            ['name' => 'OLT SE PLANTA MANAGUA', 'ip_olt' => '172.27.3.1'],
            ['name' => 'OLT-SE SAN JUAN DEL SUR', 'ip_olt' => '172.20.60.10'],
            ['name' => 'OLT-SAN JOSE DE BOCAY', 'ip_olt' => '172.27.3.28'],
            ['name' => 'OLT-PAIWAS', 'ip_olt' => '172.20.154.12'],
            ['name' => 'OLT-S/E-WASLALA', 'ip_olt' => '172.20.162.10'],
            ['name' => 'OLT-LA-LIBERTAD', 'ip_olt' => '172.27.3.31'],
            ['name' => 'OLT-S/E-SIUNA', 'ip_olt' => '172.27.3.37'],
            ['name' => 'OLT-SE-ROSITA', 'ip_olt' => '172.27.3.38'],
            ['name' => 'OLT-S/E-BILWI', 'ip_olt' => '172.27.3.39'],
            ['name' => 'OLT-KUKRA HILL', 'ip_olt' => '172.27.3.35'],
            ['name' => 'OLT-MORRITO', 'ip_olt' => '172.20.99.11'],
            ['name' => 'OLT_SN_CARLOS', 'ip_olt' => '172.20.99.13'],
            ['name' => 'OLT-SE-SAN_MIGUELITO', 'ip_olt' => '172.20.99.12'],
            ['name' => 'OLT-MULUKUKU', 'ip_olt' => '172.27.3.40'],
            ['name' => 'OLT-LAGUNA-DE-PERLAS', 'ip_olt' => '172.27.3.36'],
        ];

        // Associate OLT with ISPs
        // $activeStatusId = Status::where('code', 'active')->value('id');
        $activeStatusId = true;
        $isp_id = 1;

        foreach ($olts as $olt) {
            $olt_base_config['name'] = $olt['name'];
            $olt_base_config['ip_olt'] = $olt['ip_olt'];
            $olt = OLT::create($olt_base_config);

            $olt->isps()->attach($isp_id, [
                'relation_name' => "Relación {$olt['name']} - Itel",
                // 'relation_notes' => 'Proveedor principal',
                'status' => $activeStatusId
            ]);
        }
    }
}

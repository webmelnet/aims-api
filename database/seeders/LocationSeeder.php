<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Headquarters',
                'code' => 'HQ',
                'address' => 'Legazpi City',
                'city' => 'Legazpi',
                'state' => 'Albay',
                'country' => 'Philippines',
                'postal_code' => '4500',
                'is_active' => true,
            ],
            [
                'name' => 'Branch Office - Sorsogon',
                'code' => 'SOR-01',
                'address' => 'Sorsogon',
                'city' => 'Sorsogon',
                'state' => 'Sorsogon',
                'country' => 'Philippines',
                'postal_code' => '4700',
                'is_active' => true,
            ],
            [
                'name' => 'Branch Office - Naga City',
                'code' => 'CAMSUR-01',
                'address' => 'Naga City',
                'city' => 'Naga City',
                'state' => 'Camarenis Sur',
                'country' => 'Philippines',
                'postal_code' => '4400',
                'is_active' => true,
            ],
            [
                'name' => 'Branch Office - Daet',
                'code' => 'CAMNORTE-01',
                'address' => 'Daet',
                'city' => 'Daet',
                'state' => 'Camarines Norte',
                'country' => 'Philippines',
                'postal_code' => '4600',
                'is_active' => true,
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }

        $this->command->info('Locations seeded successfully!');
    }
}

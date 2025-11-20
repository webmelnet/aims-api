<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'Corning',
                'code' => 'COR',
                'email' => 'sales@dell.com',
                'phone' => '+1-800-289-3355',
                'address' => 'One Dell Way',
                'city' => 'Round Rock',
                'state' => 'Texas',
                'country' => 'USA',
                'postal_code' => '78682',
                'contact_person' => 'John Smith',
                'contact_phone' => '+1-800-289-3355',
                'contact_email' => 'john.smith@dell.com',
                'notes' => 'Primary computer hardware vendor',
                'is_active' => true,
            ],
            [
                'name' => 'Prysmian Group',
                'code' => 'PG',
                'email' => 'sales@pg.com',
                'phone' => '+1-800-752-0900',
                'address' => '1501 Page Mill Road',
                'city' => 'Palo Alto',
                'state' => 'California',
                'country' => 'USA',
                'postal_code' => '94304',
                'contact_person' => 'Jane Doe',
                'contact_phone' => '+1-800-752-0900',
                'contact_email' => 'jane.doe@hp.com',
                'notes' => 'Printer and laptop vendor',
                'is_active' => true,
            ],
            [
                'name' => 'CommScope',
                'code' => 'CS',
                'email' => 'licensing@commscope.com',
                'phone' => '+1-800-642-7676',
                'address' => 'One Microsoft Way',
                'city' => 'Redmond',
                'state' => 'Washington',
                'country' => 'USA',
                'postal_code' => '98052',
                'contact_person' => 'Bob Johnson',
                'contact_phone' => '+1-800-642-7676',
                'contact_email' => 'bob.johnson@microsoft.com',
                'notes' => 'Software licensing vendor',
                'is_active' => true,
            ]
        ];

        foreach ($vendors as $vendor) {
            Vendor::create($vendor);
        }

        $this->command->info('Vendors seeded successfully!');
    }
}

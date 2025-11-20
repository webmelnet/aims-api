<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Parent categories
        // $computers = AssetCategory::create([
        //     'name' => 'Computers',
        //     'code' => 'COMP',
        //     'description' => 'All computer equipment including desktops and laptops',
        //     'is_active' => true,
        // ]);

        // $peripherals = AssetCategory::create([
        //     'name' => 'Peripherals',
        //     'code' => 'PERI',
        //     'description' => 'Computer peripherals and accessories',
        //     'is_active' => true,
        // ]);

        // $furniture = AssetCategory::create([
        //     'name' => 'Furniture',
        //     'code' => 'FURN',
        //     'description' => 'Office furniture and fixtures',
        //     'is_active' => true,
        // ]);

        // $vehicles = AssetCategory::create([
        //     'name' => 'Vehicles',
        //     'code' => 'VEH',
        //     'description' => 'Company vehicles',
        //     'is_active' => true,
        // ]);

        // $software = AssetCategory::create([
        //     'name' => 'Software',
        //     'code' => 'SOFT',
        //     'description' => 'Software licenses and subscriptions',
        //     'is_active' => true,
        // ]);

        // // Sub-categories for Computers
        // AssetCategory::create([
        //     'name' => 'Laptops',
        //     'code' => 'COMP-LAP',
        //     'description' => 'Laptop computers',
        //     'parent_id' => $computers->id,
        //     'is_active' => true,
        // ]);

        // AssetCategory::create([
        //     'name' => 'Desktops',
        //     'code' => 'COMP-DESK',
        //     'description' => 'Desktop computers',
        //     'parent_id' => $computers->id,
        //     'is_active' => true,
        // ]);

        // AssetCategory::create([
        //     'name' => 'Servers',
        //     'code' => 'COMP-SERV',
        //     'description' => 'Server equipment',
        //     'parent_id' => $computers->id,
        //     'is_active' => true,
        // ]);

        // // Sub-categories for Peripherals
        // AssetCategory::create([
        //     'name' => 'Monitors',
        //     'code' => 'PERI-MON',
        //     'description' => 'Computer monitors and displays',
        //     'parent_id' => $peripherals->id,
        //     'is_active' => true,
        // ]);

        // AssetCategory::create([
        //     'name' => 'Keyboards & Mice',
        //     'code' => 'PERI-KM',
        //     'description' => 'Keyboards and mice',
        //     'parent_id' => $peripherals->id,
        //     'is_active' => true,
        // ]);

        // AssetCategory::create([
        //     'name' => 'Printers',
        //     'code' => 'PERI-PRINT',
        //     'description' => 'Printers and scanners',
        //     'parent_id' => $peripherals->id,
        //     'is_active' => true,
        // ]);

        // // Sub-categories for Furniture
        // AssetCategory::create([
        //     'name' => 'Desks',
        //     'code' => 'FURN-DESK',
        //     'description' => 'Office desks',
        //     'parent_id' => $furniture->id,
        //     'is_active' => true,
        // ]);

        // AssetCategory::create([
        //     'name' => 'Chairs',
        //     'code' => 'FURN-CHAIR',
        //     'description' => 'Office chairs',
        //     'parent_id' => $furniture->id,
        //     'is_active' => true,
        // ]);

        $computers = AssetCategory::create([
            'name' => 'Cable Wire',
            'code' => 'CBLW',
            'description' => 'Cable Wires',
            'is_active' => true,
        ]);

        $computers = AssetCategory::create([
            'name' => 'Cable Wire Peripherals',
            'code' => 'CBLW-PER',
            'description' => 'Bend Insensitive Fiber Patch Cord',
            'is_active' => true,
        ]);

        $this->command->info('Asset categories seeded successfully!');
    }
}

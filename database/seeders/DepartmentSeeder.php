<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'IT Department - Manages all technology infrastructure and support',
                'is_active' => true,
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'description' => 'Human Resources Department - Manages employee relations and recruitment',
                'is_active' => true,
            ],
            [
                'name' => 'Logistics',
                'code' => 'LG',
                'description' => 'The process of planning, implementing, and controlling the efficient flow and storage of goods, services, and related information from origin to destination',
                'is_active' => true,
            ],
            // [
            //     'name' => 'Finance',
            //     'code' => 'FIN',
            //     'description' => 'Finance Department - Manages financial operations and accounting',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Operations',
            //     'code' => 'OPS',
            //     'description' => 'Operations Department - Manages day-to-day business operations',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Marketing',
            //     'code' => 'MKT',
            //     'description' => 'Marketing Department - Manages brand and marketing strategies',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Sales',
            //     'code' => 'SALES',
            //     'description' => 'Sales Department - Manages customer relationships and sales',
            //     'is_active' => true,
            // ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }

        $this->command->info('Departments seeded successfully!');
    }
}

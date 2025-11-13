<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::create(['name' => 'Superadmin']);
        $adminRole = Role::create(['name' => 'Admin']);
        $managerRole = Role::create(['name' => 'Manager']);
        $staffRole = Role::create(['name' => 'Staff']);
        
        $admin = User::create([
            'name' => 'Superadmin',
            'email' => 'superadmin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($superAdminRole);
        $admin->createToken('auth_token')->plainTextToken;   

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($adminRole);
        $admin->createToken('auth_token')->plainTextToken;

        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole($managerRole);
        $manager->createToken('auth_token')->plainTextToken;

        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make(value: 'password'),
        ]);
        $staff->assignRole($staffRole);
        $staff->createToken('auth_token')->plainTextToken;        
    }
}

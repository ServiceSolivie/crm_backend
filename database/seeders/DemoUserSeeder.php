<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Create one demo user per role for local development/testing.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@crm.test',
                'role' => RoleEnum::SUPER_ADMIN,
            ],
            [
                'name' => 'Demo Manager',
                'email' => 'manager@crm.test',
                'role' => RoleEnum::MANAGER,
            ],
            [
                'name' => 'Demo Team Leader',
                'email' => 'teamleader@crm.test',
                'role' => RoleEnum::TEAM_LEADER,
            ],
            [
                'name' => 'Demo Agent',
                'email' => 'agent@crm.test',
                'role' => RoleEnum::AGENT,
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$data['role']->value]);
        }
    }
}

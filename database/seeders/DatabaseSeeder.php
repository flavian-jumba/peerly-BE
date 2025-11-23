<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create specific test users
        User::factory()->create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'), // password
        ]);

        User::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Carol Williams',
            'email' => 'carol@example.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'David Brown',
            'email' => 'david@example.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Emma Davis',
            'email' => 'emma@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create 15 additional random users
        User::factory(15)->create();

        $this->command->info('✅ Created 20 users successfully!');
        $this->command->info('📧 Test users: alice@example.com, bob@example.com, carol@example.com, david@example.com, emma@example.com');
        $this->command->info('🔑 Password for all test users: password');
    }
}

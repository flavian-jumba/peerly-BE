<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $created = 0;

        foreach ($users as $user) {
            // Check if profile already exists
            $existing = Profile::where('user_id', $user->id)->first();
            
            if (!$existing) {
                Profile::create([
                    'user_id' => $user->id,
                    'prefix' => 'user_' . $user->id,
                    'about' => null, // Users can add this later
                    'online_status' => false,
                    'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->name),
                ]);
                $created++;
            }
        }

        $this->command->info("Created {$created} profiles. Total profiles: " . Profile::count());
    }
}

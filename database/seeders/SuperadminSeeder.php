<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'jason@jasonhill.com.au';

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => 'Jason Hill',
                'password' => Hash::make('password'), // Change this password after first login!
                'email_verified_at' => now(),
            ]);
            $user->save();

            $this->command->info("Superadmin user created: {$email}");
            $this->command->warn('⚠️  Default password is "password" - please change it after first login!');
        } else {
            $this->command->info("Superadmin user already exists: {$email}");
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'sg.ck@ukr.net'],
            [
                'name' => 'SunFix',
                'password' => bcrypt('SunFixHlevaha'),
                'role' => 'sunfix',
            ]
        );
    }
}


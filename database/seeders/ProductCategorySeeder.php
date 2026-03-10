<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        DB::table('product_categories')->insertOrIgnore([
            ['name' => 'Мережеві однофазні інвертори', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Мережеві трифазні інвертори', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Гібридні однофазні HV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Гібридні однофазні LV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Гібридні трифазні HV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Гібридні трифазні LV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'АКБ HV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'АКБ LV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Інше', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

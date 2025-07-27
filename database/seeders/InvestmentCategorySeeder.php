<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvestmentCategory;

class InvestmentCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Betting',
            'BTP',
            'Coinbase',
            'Trade Republic',
            'Buoni Postali',
            'Investimenti Bancari',
            'Assicurazione Vita',
            'Altri Investimenti',
        ];

        foreach ($categories as $name) {
            InvestmentCategory::firstOrCreate(['name' => $name]);
        }
    }
}

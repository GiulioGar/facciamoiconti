<?php

// database/seeders/BudgetCategorySeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BudgetCategory;

class BudgetCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Personale',  'slug' => 'personale',  'sort_order' => 1],
            ['name' => 'Familiare',  'slug' => 'familiare',  'sort_order' => 2],
            ['name' => 'Extra',      'slug' => 'extra',      'sort_order' => 3],
            ['name' => 'Risparmi',   'slug' => 'risparmi',   'sort_order' => 4],
        ];

        foreach ($categories as $cat) {
            BudgetCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}


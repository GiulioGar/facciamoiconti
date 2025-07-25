<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;

class ExpenseCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Abbigliamento',             'slug' => 'abbigliamento',              'sort_order' => 1],
            ['name' => 'Auto-assicurazione',        'slug' => 'auto-assicurazione',         'sort_order' => 2],
            ['name' => 'Auto-benzina',              'slug' => 'auto-benzina',               'sort_order' => 3],
            ['name' => 'Auto-varie',                'slug' => 'auto-varie',                 'sort_order' => 4],
            ['name' => 'Barbiere/Parrucchiere',     'slug' => 'barbiere-parrucchiere',      'sort_order' => 5],
            ['name' => 'Calcetto',                  'slug' => 'calcetto',                   'sort_order' => 6],
            ['name' => 'Condominio',                'slug' => 'condominio',                 'sort_order' => 7],
            ['name' => 'Figli',                     'slug' => 'figli',                      'sort_order' => 8],
            ['name' => 'Familiari varie',           'slug' => 'familiari-varie',            'sort_order' => 9],
            ['name' => 'Gas',                       'slug' => 'gas',                        'sort_order' => 10],
            ['name' => 'Internet o mobile',         'slug' => 'internet-o-mobile',          'sort_order' => 11],
            ['name' => 'Luce',                      'slug' => 'luce',                       'sort_order' => 12],
            ['name' => 'Lavori casa',               'slug' => 'lavori-casa',                'sort_order' => 13],
            ['name' => 'Personali varie',           'slug' => 'personali-varie',            'sort_order' => 14],
            ['name' => 'Pranzo o spese lavoro',     'slug' => 'pranzo-o-spese-lavoro',      'sort_order' => 15],
            ['name' => 'Pulizia casa',              'slug' => 'pulizia-casa',               'sort_order' => 16],
            ['name' => 'Regali',                    'slug' => 'regali',                     'sort_order' => 17],
            ['name' => 'Ristoranti o uscite',       'slug' => 'ristoranti-o-uscite',        'sort_order' => 18],
            ['name' => 'Spazzatura',                'slug' => 'spazzatura',                 'sort_order' => 19],
            ['name' => 'Spese mediche',             'slug' => 'spese-mediche',              'sort_order' => 20],
            ['name' => 'Spese quotidiane',          'slug' => 'spese-quotidiane',           'sort_order' => 21],
            ['name' => 'Stadio',                    'slug' => 'stadio',                     'sort_order' => 22],
            ['name' => 'Uscite personali',          'slug' => 'uscite-personali',           'sort_order' => 23],
            ['name' => 'Vacanze',                   'slug' => 'vacanze',                    'sort_order' => 24],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}

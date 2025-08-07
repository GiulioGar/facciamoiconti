<?php

namespace App\Helpers;

class CategoryHelper
{
    public static function getCategoryIcons(): array
    {
        return [
            'abbigliamento'        => ['icon' => 'person-standing-dress', 'color' => 'text-info'],
            'auto-assicurazione'   => ['icon' => 'shield-check',          'color' => 'text-info'],
            'auto-benzina'         => ['icon' => 'fuel-pump',             'color' => 'text-warning'],
            'auto-varie'           => ['icon' => 'car-front-fill',        'color' => 'text-primary'],
            'barbiere-parrucchiere'=> ['icon' => 'scissors',        'color' => 'text-primary'],
            'calcetto'             => ['icon' => 'cookie',        'color' => 'text-primary'],
            'condominio'           => ['icon' => 'building',              'color' => 'text-secondary'],
            'figli'                => ['icon' => 'emoji-smile',           'color' => 'text-success'],
            'familiari-varie'      => ['icon' => 'people',                'color' => 'text-muted'],
            'gas'                  => ['icon' => 'wind',                  'color' => 'text-warning'],
            'internet o mobile'    => ['icon' => 'wifi',                  'color' => 'text-primary'],
            'luce'                 => ['icon' => 'lightning',             'color' => 'text-warning'],
            'lavori-casa'          => ['icon' => 'hammer',                'color' => 'text-danger'],
            'personali-varie'       => ['icon' => 'incognito',                'color' => 'text-danger'],
            'pranzo-o-spese-lavoro' => ['icon' => 'person-workspace',   'color' => 'text-info'],
            'pulizia-casa'         => ['icon' => 'droplet',               'color' => 'text-info'],
            'regali'               => ['icon' => 'gift',                  'color' => 'text-success'],
            'ristoranti-o-uscite'  => ['icon' => 'cup-straw',             'color' => 'text-danger'],
            'spazzatura'           => ['icon' => 'trash3',                'color' => 'text-muted'],
            'spese mediche'        => ['icon' => 'heart-pulse',           'color' => 'text-danger'],
            'spese-quotidiane'     => ['icon' => 'cart-check',            'color' => 'text-primary'],
            'stadio'               => ['icon' => 'cookie',            'color' => 'text-primary'],
            'uscite-personali'     => ['icon' => 'person-badge',            'color' => 'text-primary'],
            'vacanze'              => ['icon' => 'suitcase',              'color' => 'text-warning'],
        ];
    }
}

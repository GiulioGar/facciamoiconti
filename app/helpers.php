<?php

use App\Helpers\CategoryHelper;

if (!function_exists('category_icons')) {
    function category_icons(): array {
        return CategoryHelper::getCategoryIcons();
    }
}

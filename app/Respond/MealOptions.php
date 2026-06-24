<?php
declare(strict_types=1);

namespace App\Respond;

final class MealOptions
{
    public const CHOICES = [
        ['key' => 'coffee',  'label' => 'Coffee',  'icon' => 'ic-coffee'],
        ['key' => 'brunch',  'label' => 'Brunch',  'icon' => 'ic-sparkle'],
        ['key' => 'lunch',   'label' => 'Lunch',   'icon' => 'ic-utensils'],
        ['key' => 'dinner',  'label' => 'Dinner',  'icon' => 'ic-utensils'],
        ['key' => 'dessert', 'label' => 'Dessert', 'icon' => 'ic-heart'],
        ['key' => 'drinks',  'label' => 'Drinks',  'icon' => 'ic-wine'],
    ];

    /** @return string[] */
    public static function keys(): array
    {
        return array_column(self::CHOICES, 'key');
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }
}

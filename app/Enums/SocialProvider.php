<?php

namespace App\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

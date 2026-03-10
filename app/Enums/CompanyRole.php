<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';

    public static function managerRoles(): array
    {
        return [self::Owner, self::Admin];
    }
}

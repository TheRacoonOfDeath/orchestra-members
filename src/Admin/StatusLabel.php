<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

final class StatusLabel
{
    public static function of(bool $active): string
    {
        return $active ? \__('Active', 'orchestra-members') : \__('Inactive', 'orchestra-members');
    }
}

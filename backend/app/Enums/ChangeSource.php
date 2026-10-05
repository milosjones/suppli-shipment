<?php

namespace App\Enums;

enum ChangeSource: string
{
    case Manual = 'manual';

    case Api = 'api';

    case Import = 'import';

    case Provider = 'provider';

    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Api => 'API',
            self::Import => 'Import',
            self::Provider => 'Tracking provider',
            self::System => 'Automatic',
        };
    }
}

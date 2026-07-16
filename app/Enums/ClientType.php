<?php

namespace App\Enums;

enum ClientType: string
{
    case NationalFederation = 'national_federation';
    case ExternalClient = 'external_client';

    public function label(): string
    {
        return match ($this) {
            self::NationalFederation => 'National Federation',
            self::ExternalClient => 'External Client',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

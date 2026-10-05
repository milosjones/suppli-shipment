<?php

namespace App\Services\Shipments;

use Illuminate\Support\Facades\DB;

final class ShipmentReference
{
    public static function next(): array
    {
        $id = (int) DB::selectOne("SELECT nextval(pg_get_serial_sequence('shipments', 'id')) AS id")->id;

        return [$id, self::format($id)];
    }

    public static function format(int $id): string
    {
        return sprintf('SHP-%05d', $id);
    }
}

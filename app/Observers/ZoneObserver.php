<?php

namespace App\Observers;

use App\Models\Zone;

class ZoneObserver
{
    public function deleting(Zone $zone): void
    {
        $zone->assertDeletable();
    }
}

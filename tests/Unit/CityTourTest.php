<?php

namespace Tests\Unit;

use App\Models\CityTour;
use PHPUnit\Framework\TestCase;

class CityTourTest extends TestCase
{
    public function test_it_exposes_date_alias_from_scheduled_date(): void
    {
        $tour = new CityTour([
            'scheduled_date' => '2026-09-15',
        ]);

        $this->assertSame('2026-09-15', $tour->date);
        $this->assertSame('2026-09-15', $tour->toArray()['date']);
        $this->assertSame('2026-09-15', $tour->toArray()['scheduled_date']);
    }
}

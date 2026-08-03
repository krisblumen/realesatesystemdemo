<?php

namespace Tests\Unit;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use PHPUnit\Framework\TestCase;

class PropertyEnumsTest extends TestCase
{
    public function test_operation_and_property_types_expose_the_approved_values(): void
    {
        $this->assertSame(['venta', 'renta'], array_column(OperationType::cases(), 'value'));
        $this->assertSame(
            ['casa', 'departamento', 'terreno', 'local', 'oficina', 'bodega'],
            array_column(PropertyType::cases(), 'value'),
        );
    }

    public function test_only_published_status_is_public(): void
    {
        foreach (PropertyStatus::cases() as $status) {
            $this->assertSame($status === PropertyStatus::Publicado, $status->isPublic());
        }
    }

    public function test_status_transitions_match_the_commercial_state_machine(): void
    {
        $this->assertSame([PropertyStatus::Publicado], PropertyStatus::Borrador->allowedTransitions());
        $this->assertSame(
            [PropertyStatus::Pausado, PropertyStatus::Vendido, PropertyStatus::Rentado],
            PropertyStatus::Publicado->allowedTransitions(),
        );
        $this->assertSame(
            [PropertyStatus::Publicado, PropertyStatus::Vendido, PropertyStatus::Rentado],
            PropertyStatus::Pausado->allowedTransitions(),
        );
        $this->assertTrue(PropertyStatus::Vendido->canTransitionTo(PropertyStatus::Borrador));
        $this->assertTrue(PropertyStatus::Rentado->canTransitionTo(PropertyStatus::Borrador));
        $this->assertFalse(PropertyStatus::Borrador->canTransitionTo(PropertyStatus::Vendido));
    }
}

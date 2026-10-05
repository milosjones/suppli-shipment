<?php

namespace Tests\Unit;

use App\Enums\CargoStatus;
use PHPUnit\Framework\TestCase;

class CargoStatusTest extends TestCase
{
    public function test_the_flow_only_moves_forward_one_step(): void
    {
        $this->assertTrue(CargoStatus::Planned->canMoveTo(CargoStatus::InTransit));
        $this->assertTrue(CargoStatus::InTransit->canMoveTo(CargoStatus::Arrived));
        $this->assertTrue(CargoStatus::Arrived->canMoveTo(CargoStatus::InStorage));
        $this->assertTrue(CargoStatus::InStorage->canMoveTo(CargoStatus::Delivered));

        $this->assertFalse(CargoStatus::Planned->canMoveTo(CargoStatus::Arrived), 'cannot skip departure');
        $this->assertFalse(CargoStatus::Arrived->canMoveTo(CargoStatus::InTransit), 'cannot move backwards');
        $this->assertSame([], CargoStatus::Delivered->nextStatuses());
    }

    public function test_cargo_can_be_delivered_directly_from_the_port(): void
    {
        $this->assertTrue(CargoStatus::Arrived->canMoveTo(CargoStatus::Delivered));
    }

    public function test_only_last_mile_statuses_target_single_items(): void
    {
        $this->assertTrue(CargoStatus::InStorage->canTargetSingleItems());
        $this->assertTrue(CargoStatus::Delivered->canTargetSingleItems());
        $this->assertFalse(CargoStatus::InTransit->canTargetSingleItems());
        $this->assertFalse(CargoStatus::Arrived->canTargetSingleItems());
    }

    public function test_path_to_a_later_status(): void
    {
        $this->assertSame([CargoStatus::Arrived, CargoStatus::Delivered], CargoStatus::InTransit->pathTo(CargoStatus::Delivered));
        $this->assertSame([], CargoStatus::Arrived->pathTo(CargoStatus::Arrived));
        $this->assertNull(CargoStatus::Delivered->pathTo(CargoStatus::InTransit));
    }

    public function test_least_advanced_status_summarises_mixed_cargo(): void
    {
        $this->assertSame(CargoStatus::InStorage, CargoStatus::leastAdvanced(['delivered', 'in_storage', 'delivered']));
        $this->assertSame(CargoStatus::Delivered, CargoStatus::leastAdvanced([CargoStatus::Delivered]));
        $this->assertNull(CargoStatus::leastAdvanced([]));
    }
}

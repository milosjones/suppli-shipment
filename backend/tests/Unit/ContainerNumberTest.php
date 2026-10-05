<?php

namespace Tests\Unit;

use App\Enums\ShippingMethod;
use App\Support\ContainerNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContainerNumberTest extends TestCase
{
    public static function containersFromTheDeliveryPlan(): array
    {
        return array_map(fn (string $number) => [$number], [
            'HLBU8073625' => 'HLBU8073625',
            'QIBU1242915' => 'QIBU1242915',
            'MEDU4711735' => 'MEDU4711735',
            'HAMU3021465' => 'HAMU3021465',
            'UETU6194142' => 'UETU6194142',
            'FANU3240660' => 'FANU3240660',
        ]);
    }

    #[DataProvider('containersFromTheDeliveryPlan')]
    public function test_real_container_numbers_are_valid(string $number): void
    {
        $this->assertTrue(ContainerNumber::isValidIso6346($number));
    }

    public function test_wrong_check_digit_is_rejected(): void
    {
        $this->assertFalse(ContainerNumber::isValidIso6346('HLBU8073626'));
    }

    public function test_remainder_ten_is_written_as_check_digit_zero(): void
    {
        $this->assertSame(0, ContainerNumber::iso6346CheckDigit('HLBU100000'));
        $this->assertTrue(ContainerNumber::isValidIso6346('HLBU1000000'));
    }

    public function test_format_is_checked(): void
    {
        $this->assertFalse(ContainerNumber::isValidIso6346('HLB8073625'));
        $this->assertFalse(ContainerNumber::isValidIso6346('HLBX8073625'), 'category letter must be U, J or Z');
        $this->assertFalse(ContainerNumber::isValidIso6346('HLBU807362'));
    }

    public function test_numbers_are_normalised_before_validation(): void
    {
        $this->assertSame('HLBU8073625', ContainerNumber::normalize(' hlbu 807 3625 '));
        $this->assertTrue(ContainerNumber::isValidFor(ShippingMethod::Sea, 'hlbu 8073625'));
        $this->assertSame('501-20079076', ContainerNumber::normalize('50120079076'));
    }

    public function test_air_waybills_use_the_modulo_seven_check_digit(): void
    {
        $this->assertTrue(ContainerNumber::isValidAirWaybill('501-20079076'));
        $this->assertTrue(ContainerNumber::isValidAirWaybill('501-20079065'));
        $this->assertFalse(ContainerNumber::isValidAirWaybill('501-20079077'));
        $this->assertFalse(ContainerNumber::isValidAirWaybill('HLBU8073625'));
    }

    public function test_the_number_must_match_the_shipping_method(): void
    {
        $this->assertFalse(ContainerNumber::isValidFor(ShippingMethod::Sea, '501-20079076'));
        $this->assertFalse(ContainerNumber::isValidFor(ShippingMethod::Air, 'HLBU8073625'));
        $this->assertTrue(ContainerNumber::isValidFor(ShippingMethod::Road, 'AB-12345'));
        $this->assertTrue(ContainerNumber::isValidFor(ShippingMethod::Road, 'HLBU8073625'));
    }

    public function test_the_method_can_be_detected_from_the_format(): void
    {
        $this->assertSame(ShippingMethod::Sea, ContainerNumber::detectMethod('HLBU8073625'));
        $this->assertSame(ShippingMethod::Air, ContainerNumber::detectMethod('501-20079076'));
        $this->assertNull(ContainerNumber::detectMethod('unknown'));
    }
}

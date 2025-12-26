<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\Phaster\Prop;
use PHPUnit\Framework\TestCase;

class PropTest extends TestCase
{
    public function testInvalidConstruction(): void
    {
        try {
            new Prop('lastName', 'LastName', true);
            $this->fail('Failed to throw exception for invalid nullGroup');
        } catch (\Exception $e) {
            $this->assertSame("nullGroup cannot be set on top-level lastName property", $e->getMessage());
        }

        try {
            new Prop('test', 'test', false, true, '', 'array');
            $this->fail('Failed to throw exception for invalid Prop type');
        } catch (\Exception $e) {
            $this->assertSame("type for test property must be bool, int, float, or string", $e->getMessage());
        }

        $getValue = fn(array $_row): string => '';

        try {
            new Prop('foo', 'foo', false, true, '', 'bool', false, $getValue);
            $this->fail('Failed to throw exception for type set along with getValue');
        } catch (\Exception $e) {
            $this->assertSame("type cannot be set on foo property along with getValue", $e->getMessage());
        }

        try {
            new Prop('bar', 'bar', false, true, '', null, null, $getValue);
            $this->fail('Failed to throw exception for timeZone set along with getValue');
        } catch (\Exception $e) {
            $this->assertSame("timeZone cannot be set on bar property along with getValue", $e->getMessage());
        }

        try {
            new Prop('isBillable', 'isBillable', false, true, '', null, false, null, ['isDisabled']);
            $this->fail('Failed to throw exception for dependsOn key without getValue function');
        } catch (\Exception $e) {
            $this->assertSame("dependsOn cannot be used on isBillable property without getValue function", $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use Ancarda\Horcrux\Horcrux;
use PHPUnit\Framework\TestCase;

final class HorcruxTest extends TestCase
{
    public function testStoreAndRetrieveValue(): void
    {
        $horcrux = Horcrux::createFromValue('hello world');
        static::assertSame('hello world', $horcrux->getValue());
    }

    public function testValueSurvivesClone(): void
    {
        $first = Horcrux::createFromValue('hello world');
        $second = clone $first;
        unset($first);
        static::assertSame('hello world', $second->getValue());
    }

    public function testValueIsClearedFromMemory(): void
    {
        $horcrux = Horcrux::createFromValue(42);
        static::assertSame([42], array_values($GLOBALS['horcrux_value']));
        unset($horcrux);
        static::assertSame([], $GLOBALS['horcrux_value']);
    }

    public function testConcurrency(): void
    {
        $A_first = Horcrux::createFromValue(42);
        $B_first = Horcrux::createFromValue(true);
        $A_second = clone $A_first;
        $B_second = clone $B_first;

        static::assertSame(42, $A_second->getValue());
        static::assertTrue($B_second->getValue());
    }

    public function testGenerateIds(): void
    {
        $horcruxA = Horcrux::createFromValue('');
        $value = array_keys($GLOBALS['horcrux_value'])[0];
        static::assertIsString($value);
        static::assertSame(32, strlen($value));

        $horcruxB = Horcrux::createFromValue('');
        $ids = array_keys($GLOBALS['horcrux_pointers']);
        static::assertNotSame($ids[0], $ids[1]);
        static::assertIsString($ids[0]);
        static::assertSame(32, strlen($ids[0]));
        static::assertSame($value, $GLOBALS['horcrux_pointers'][$ids[0]]);

        $cloned = clone $horcruxA;
        $newId = array_keys($GLOBALS['horcrux_pointers'])[2];
        static::assertIsString($newId);
        static::assertSame(32, strlen($newId));
        static::assertNotSame($newId, $ids[0]);
        static::assertNotSame($newId, $ids[1]);
    }
}

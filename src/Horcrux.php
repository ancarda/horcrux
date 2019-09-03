<?php

declare(strict_types=1);

namespace Ancarda\Horcrux;

use Exception;

/**
 * Container for massive amounts of data that's cheap and fast to clone
 *
 * Horcrux is a container that keeps a reference to a globally stored variable
 * that can be cloned and destroyed very cheaply. To make an initial horcrux:
 *
 *     $horcrux = Horcrux::createFromValue("some expensive data to clone...");
 *
 * You can now keep $horcrux inside a class alongside other values. When your
 * class is cloned, the horcrux is also cloned. Whenever you want the value
 * inside, just call the getValue method:
 *
 *     $this->horcrux->getValue();
 *
 * You can clone the horcrux as many times as you'd like. Whenever you destory
 * one, the value it's tethered to remains alive. When the last horcrux is
 * destroyed, the value it's tied to will also be destroyed.
 *
 * A primary use case might be keeping large values inside immutable objects,
 * such as the body of a PSR-7 request when the StreamInterface implementation
 * uses a string
 *
 * To change the value inside the horcrux, just destroy it and make a new one.
 * The value shouldn't hang around unless you have clones of other parent
 * objects hanging around, in which case you probably want the values to be
 * retained. In this fashion, Horcrux behaves like a COW (Copy on Write)
 * system.
 *
 * WARNING: You should not use Horcrux to store sensitive data! All data kept
 * around in this fashion is in globally accessible memory!
 */
final class Horcrux
{
    /** @var string */
    private $horcruxId;

    /**
     * @param string $horcruxId
     */
    private function __construct(string $horcruxId)
    {
        $this->horcruxId = $horcruxId;
    }

    /**
     * @param mixed $value
     * @throws Exception If a new ID cannot be generated
     * @return Horcrux
     */
    public static function createFromValue($value): self
    {
        $valueId   = bin2hex(random_bytes(16));
        $horcruxId = bin2hex(random_bytes(16));

        if (! isset($GLOBALS['horcrux_value'])) {
            $GLOBALS['horcrux_value'] = [];
        }

        if (! isset($GLOBALS['horcrux_pointers'])) {
            $GLOBALS['horcrux_pointers'] = [];
        }

        $GLOBALS['horcrux_value'][$valueId]      = $value;
        $GLOBALS['horcrux_pointers'][$horcruxId] = $valueId;

        return new self($horcruxId);
    }

    /**
     * @throws Exception If a new ID cannot be generated
     */
    public function __clone()
    {
        $valueId = $GLOBALS['horcrux_pointers'][$this->horcruxId];

        // New horcrux, new ID
        $newHorcruxId = bin2hex(random_bytes(16));
        $GLOBALS['horcrux_pointers'][$newHorcruxId] = $valueId;
        $this->horcruxId = $newHorcruxId;
    }

    public function __destruct()
    {
        $myValueId = $GLOBALS['horcrux_pointers'][$this->horcruxId];
        unset($GLOBALS['horcrux_pointers'][$this->horcruxId]);

        $survivors = array_filter(
            $GLOBALS['horcrux_pointers'],
            function ($valueId) use ($myValueId): bool {
                return $valueId === $myValueId;
            }
        );

        // If this was the last horcrux, destroy the value too
        if (count($survivors) === 0) {
            unset($GLOBALS['horcrux_value'][$myValueId]);
        }
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $GLOBALS['horcrux_value'][$GLOBALS['horcrux_pointers'][$this->horcruxId]];
    }
}

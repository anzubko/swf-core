<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AsListener
{
    /**
     * Registers listener.
     *
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     */
    public function __construct(
        public float $priority = 0.0,
        public bool $disposable = false,
        public bool $persistent = false,
    ) {
    }
}

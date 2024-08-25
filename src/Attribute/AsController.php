<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsController
{
    /**
     * Registers controller.
     *
     * @param string|string[] $url Urls of controller.
     * @param string|string[] $method Allowed HTTP methods of controller.
     * @param string|null $alias Alias of controller.
     */
    public function __construct(
        public string|array $url,
        public string|array $method = [],
        public ?string $alias = null,
    ) {
    }
}

<?php
declare(strict_types=1);

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
        private string|array $url,
        private string|array $method = [],
        private ?string $alias = null,
    ) {
    }

    /**
     * @return string|string[]
     */
    public function getUrl(): array|string
    {
        return $this->url;
    }

    /**
     * @return string|string[]
     */
    public function getMethod(): array|string
    {
        return $this->method;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}

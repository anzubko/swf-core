<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use function count;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AsController
{
    /** @var string[] */
    public array $url;

    /** @var string[] */
    public array $method;

    public ?string $alias;

    /**
     * Registers controller.
     *
     * @param string|string[] $url
     * @param string|string[] $method
     */
    public function __construct(string|array $url, string|array $method = [], ?string $alias = null)
    {
        $this->url = (array) $url;

        $this->method = (array) $method;

        if (count($this->method) > 0) {
            $this->method = array_map(strtoupper(...), $this->method);
        } else {
            $this->method[] = '';
        }

        $this->alias = $alias;
    }
}

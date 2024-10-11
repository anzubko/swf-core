<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\ActionTypeEnum;

final readonly class CurrentAction
{
    public function __construct(
        private ActionTypeEnum $type,
        private ?string $method = null,
        private ?string $alias = null,
    ) {
    }

    public function getType(): ActionTypeEnum
    {
        return $this->type;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}

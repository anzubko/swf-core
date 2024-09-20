<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\ActionTypeEnum;

final readonly class CurrentActionInfo
{
    public function __construct(
        public ActionTypeEnum $type,
        public ?string $method = null,
        public ?string $alias = null,
    ) {
    }
}

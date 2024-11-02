<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\DatabaserInterface;

final readonly class TransactionDeclaration
{
    /**
     * @param DatabaserInterface $db Which database connection use for transaction.
     * @param string|null $isolation Needed isolation level.
     * @param string[] $states SQL states allowed for retries.
     */
    public function __construct(
        public DatabaserInterface $db,
        public ?string $isolation = null,
        public array $states = [],
    ) {
    }
}

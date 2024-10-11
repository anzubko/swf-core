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
        private DatabaserInterface $db,
        private ?string $isolation = null,
        private array $states = [],
    ) {
    }

    public function getDb(): DatabaserInterface
    {
        return $this->db;
    }

    public function getIsolation(): ?string
    {
        return $this->isolation;
    }

    /**
     * @return string[]
     */
    public function getStates(): array
    {
        return $this->states;
    }
}

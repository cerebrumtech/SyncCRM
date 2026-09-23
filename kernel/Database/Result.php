<?php

namespace Sync\Database;

class Result
{
    /** @var array<int,array<string,mixed>> */
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function getResultArray(): array
    {
        return $this->rows;
    }

    /** @return array<string,mixed>|null */
    public function getRowArray()
    {
        return $this->rows[0] ?? null;
    }

    public function getNumRows(): int
    {
        return count($this->rows);
    }
}

<?php

namespace Sync;

use Sync\Database\QueryBuilder;

/**
 * Array-returning active record. Subclasses declare $table, $allowedFields and $casts;
 * casts translate between MySQL columns and PHP types on the way in and out.
 */
abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    /** @var array<int,string> */
    protected $allowedFields = [];
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    /** @var array<string,string> column => cast type */
    protected array $casts = [];

    /** @var QueryBuilder|null */
    private $builder;

    protected function db()
    {
        return db_connect();
    }

    public function builder(): QueryBuilder
    {
        if ($this->builder === null) {
            $this->builder = $this->db()->table($this->table);
        }
        return $this->builder;
    }

    private function done(): void
    {
        $this->builder = null;
    }

    // ---- builder passthrough ---------------------------------------------------------------

    public function select(string $f = '*'): self       { $this->builder()->select($f); return $this; }
    public function distinct(bool $on = true): self     { $this->builder()->distinct($on); return $this; }
    public function join(string $t, string $c, string $type = ''): self { $this->builder()->join($t, $c, $type); return $this; }
    public function where($k, $v = QueryBuilder::NO_VALUE, bool $e = true): self { $this->builder()->where($k, $v, $e); return $this; }
    public function orWhere($k, $v = QueryBuilder::NO_VALUE, bool $e = true): self { $this->builder()->orWhere($k, $v, $e); return $this; }
    public function whereIn(string $f, array $v): self  { $this->builder()->whereIn($f, $v); return $this; }
    public function whereNotIn(string $f, array $v): self { $this->builder()->whereNotIn($f, $v); return $this; }
    public function orWhereIn(string $f, array $v): self { $this->builder()->orWhereIn($f, $v); return $this; }
    public function like(string $f, string $v): self    { $this->builder()->like($f, $v); return $this; }
    public function orLike(string $f, string $v): self  { $this->builder()->orLike($f, $v); return $this; }
    public function groupStart(): self                  { $this->builder()->groupStart(); return $this; }
    public function orGroupStart(): self                { $this->builder()->orGroupStart(); return $this; }
    public function groupEnd(): self                    { $this->builder()->groupEnd(); return $this; }
    public function groupBy(string $f): self            { $this->builder()->groupBy($f); return $this; }
    public function orderBy(string $f, string $d = 'ASC', bool $e = true): self { $this->builder()->orderBy($f, $d, $e); return $this; }
    public function limit(int $l, int $o = 0): self     { $this->builder()->limit($l, $o); return $this; }
    public function set($k, $v = QueryBuilder::NO_VALUE, bool $e = true): self { $this->builder()->set($k, $v, $e); return $this; }

    // ---- reads --------------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function find($id)
    {
        $row = $this->builder()->where($this->table . '.' . $this->primaryKey, $id)->limit(1)->get()->getRowArray();
        $this->done();
        return $row === null ? null : $this->fromDb($row);
    }

    public function findAll(int $limit = 0, int $offset = 0): array
    {
        if ($limit > 0) {
            $this->builder()->limit($limit, $offset);
        }
        $rows = $this->builder()->get()->getResultArray();
        $this->done();
        return array_map([$this, 'fromDb'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function first()
    {
        $row = $this->builder()->limit(1)->get()->getRowArray();
        $this->done();
        return $row === null ? null : $this->fromDb($row);
    }

    public function countAllResults(bool $reset = true): int
    {
        $n = $this->builder()->countAllResults($reset);
        if ($reset) {
            $this->done();
        }
        return $n;
    }

    // ---- writes -------------------------------------------------------------------------

    public function insert(array $data): int
    {
        $data = $this->toDb($this->filter($data));
        if ($this->useTimestamps) {
            $now = date('Y-m-d H:i:s');
            if ($this->createdField !== '' && ! isset($data[$this->createdField])) {
                $data[$this->createdField] = $now;
            }
            if ($this->updatedField !== '' && ! isset($data[$this->updatedField])) {
                $data[$this->updatedField] = $now;
            }
        }
        $id = $this->builder()->insert($data);
        $this->done();
        return $id;
    }

    /** update($id, $data) or ->where(...)->set(...)->update() */
    public function update($id = null, array $data = null): bool
    {
        if ($id === null && $data === null) {
            $ok = $this->builder()->update();
            $this->done();
            return $ok;
        }
        $data = $this->toDb($this->filter((array) $data));
        if ($this->useTimestamps && $this->updatedField !== '' && ! isset($data[$this->updatedField])) {
            $data[$this->updatedField] = date('Y-m-d H:i:s');
        }
        $ok = $this->builder()->where($this->primaryKey, $id)->update($data);
        $this->done();
        return $ok;
    }

    public function delete($id = null): bool
    {
        if ($id !== null) {
            $this->builder()->where($this->primaryKey, $id);
        }
        $ok = $this->builder()->delete();
        $this->done();
        return $ok;
    }

    // ---- casting ------------------------------------------------------------------------

    private function filter(array $data): array
    {
        if (! $this->allowedFields) {
            return $data;
        }
        $allowed = array_flip($this->allowedFields);
        return array_intersect_key($data, $allowed);
    }

    /** Database row to PHP values. */
    public function fromDb(array $row): array
    {
        foreach ($this->casts as $col => $type) {
            if (! array_key_exists($col, $row)) {
                continue;
            }
            $v = $row[$col];
            $nullable = strpos($type, '?') === 0;
            $base     = ltrim($type, '?');
            if ($v === null) {
                $row[$col] = $nullable ? null : ($base === 'json-array' ? [] : $v);
                continue;
            }
            if ($base === 'int') {
                $row[$col] = (int) $v;
            } elseif ($base === 'float') {
                $row[$col] = (float) $v;
            } elseif ($base === 'int-bool' || $base === 'bool') {
                $row[$col] = (bool) $v;
            } elseif ($base === 'json-array') {
                $decoded   = is_array($v) ? $v : json_decode((string) $v, true);
                $row[$col] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    /** PHP values to database columns. */
    private function toDb(array $data): array
    {
        foreach ($data as $col => $v) {
            $type = $this->casts[$col] ?? null;
            if ($type !== null && ltrim($type, '?') === 'json-array') {
                // Encode as-is: a list stays a JSON array, a map stays a JSON object.
                $data[$col] = json_encode(is_array($v) ? $v : []);
                continue;
            }
            if (is_array($v)) {
                $data[$col] = json_encode($v);
            } elseif (is_bool($v)) {
                $data[$col] = $v ? 1 : 0;
            }
        }
        return $data;
    }
}

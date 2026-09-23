<?php

namespace Sync\Database;

use InvalidArgumentException;

/**
 * Builds SELECT/INSERT/UPDATE/DELETE with bound parameters.
 * Only the subset of the CodeIgniter builder API this application uses is implemented.
 */
class QueryBuilder
{
    /** Marker so where('x IS NULL') can be told apart from where('x', null). */
    const NO_VALUE = "\0__no_value__\0";

    private $db;
    private $table;
    private $select   = '*';
    private $distinct = false;
    private $joins    = [];
    /** @var array<int,array<string,mixed>> where/group tokens */
    private $wheres   = [];
    private $groupBy  = [];
    private $orderBy  = [];
    private $limit;
    private $offset   = 0;
    private $sets     = [];
    private $setParams = [];

    public function __construct(Connection $db, string $table)
    {
        $this->db    = $db;
        $this->table = $table;
    }

    // ---- SELECT parts -------------------------------------------------------------------

    public function select(string $fields = '*'): self
    {
        $this->select = $fields;
        return $this;
    }

    public function distinct(bool $on = true): self
    {
        $this->distinct = $on;
        return $this;
    }

    public function join(string $table, string $cond, string $type = ''): self
    {
        $type = strtoupper(trim($type));
        if ($type !== '' && ! in_array($type, ['LEFT', 'RIGHT', 'INNER', 'OUTER', 'LEFT OUTER', 'RIGHT OUTER'], true)) {
            throw new InvalidArgumentException('Unsupported join type: ' . $type);
        }
        $this->joins[] = trim($type . ' JOIN ' . $table . ' ON ' . $cond);
        return $this;
    }

    // ---- WHERE --------------------------------------------------------------------------

    private function addCondition(string $sql, array $params, string $bool): self
    {
        $this->wheres[] = ['t' => 'cond', 'bool' => $bool, 'sql' => $sql, 'params' => $params];
        return $this;
    }

    /**
     * where('a', 1) | where('a >=', $v) | where(['a' => 1, 'b' => 2])
     * where('raw sql')            -- one argument, used verbatim
     * where('raw sql', null, false) -- escape disabled, used verbatim
     */
    public function where($key, $value = self::NO_VALUE, bool $escape = true, string $bool = 'AND'): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->where($k, $v, $escape, $bool);
            }
            return $this;
        }
        if ($value === self::NO_VALUE || $escape === false) {
            return $this->addCondition($key, [], $bool);
        }
        $field = trim($key);
        $op    = '=';
        if (preg_match('/^(.*?)\s*(>=|<=|<>|!=|=|>|<)$/', $field, $m)) {
            $field = trim($m[1]);
            $op    = $m[2];
        }
        if ($value === null) {
            return $this->addCondition($field . ($op === '=' ? ' IS NULL' : ' IS NOT NULL'), [], $bool);
        }
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }
        return $this->addCondition($field . ' ' . $op . ' ?', [$value], $bool);
    }

    public function orWhere($key, $value = self::NO_VALUE, bool $escape = true): self
    {
        return $this->where($key, $value, $escape, 'OR');
    }

    public function whereIn(string $field, array $values, string $bool = 'AND'): self
    {
        if (! $values) {
            return $this->addCondition('1 = 0', [], $bool);
        }
        $marks = implode(', ', array_fill(0, count($values), '?'));
        return $this->addCondition($field . ' IN (' . $marks . ')', array_values($values), $bool);
    }

    public function orWhereIn(string $field, array $values): self
    {
        return $this->whereIn($field, $values, 'OR');
    }

    public function whereNotIn(string $field, array $values, string $bool = 'AND'): self
    {
        if (! $values) {
            return $this;
        }
        $marks = implode(', ', array_fill(0, count($values), '?'));
        return $this->addCondition($field . ' NOT IN (' . $marks . ')', array_values($values), $bool);
    }

    public function like(string $field, string $value, string $bool = 'AND'): self
    {
        return $this->addCondition($field . ' LIKE ?', ['%' . $this->escapeLike($value) . '%'], $bool);
    }

    public function orLike(string $field, string $value): self
    {
        return $this->like($field, $value, 'OR');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function groupStart(string $bool = 'AND'): self
    {
        $this->wheres[] = ['t' => 'open', 'bool' => $bool];
        return $this;
    }

    public function orGroupStart(): self
    {
        return $this->groupStart('OR');
    }

    public function groupEnd(): self
    {
        $this->wheres[] = ['t' => 'close'];
        return $this;
    }

    public function groupBy(string $field): self
    {
        $this->groupBy[] = $field;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC', bool $escape = true): self
    {
        if ($escape === false) {
            $this->orderBy[] = $field . ($direction !== '' ? ' ' . $this->direction($direction) : '');
        } else {
            $this->orderBy[] = $field . ' ' . $this->direction($direction);
        }
        return $this;
    }

    private function direction(string $d): string
    {
        return strtoupper(trim($d)) === 'DESC' ? 'DESC' : 'ASC';
    }

    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit  = max(0, $limit);
        $this->offset = max(0, $offset);
        return $this;
    }

    // ---- SET (for update) ----------------------------------------------------------------

    public function set($key, $value = self::NO_VALUE, bool $escape = true): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v, $escape);
            }
            return $this;
        }
        if ($escape === false) {
            $this->sets[] = $key . ' = ' . $value;
            return $this;
        }
        $this->sets[] = $key . ' = ?';
        $this->setParams[] = is_bool($value) ? ($value ? 1 : 0) : $value;
        return $this;
    }

    // ---- compilation ---------------------------------------------------------------------

    private function compileWhere(array &$params): string
    {
        if (! $this->wheres) {
            return '';
        }
        $sql      = '';
        $needBool = false;
        foreach ($this->wheres as $tok) {
            if ($tok['t'] === 'open') {
                $sql .= ($needBool ? ' ' . $tok['bool'] . ' ' : '') . '(';
                $needBool = false;
            } elseif ($tok['t'] === 'close') {
                $sql .= ')';
                $needBool = true;
            } else {
                $sql .= ($needBool ? ' ' . $tok['bool'] . ' ' : '') . $tok['sql'];
                foreach ($tok['params'] as $p) {
                    $params[] = $p;
                }
                $needBool = true;
            }
        }
        return $sql === '' ? '' : ' WHERE ' . $sql;
    }

    private function compileSelect(string $fields, bool $withOrderAndLimit, array &$params): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $fields . ' FROM ' . $this->table;
        foreach ($this->joins as $j) {
            $sql .= ' ' . $j;
        }
        $sql .= $this->compileWhere($params);
        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        if ($withOrderAndLimit) {
            if ($this->orderBy) {
                $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
            }
            if ($this->limit !== null) {
                $sql .= ' LIMIT ' . (int) $this->limit . ' OFFSET ' . (int) $this->offset;
            }
        }
        return $sql;
    }

    // ---- execution -----------------------------------------------------------------------

    public function get(): Result
    {
        $params = [];
        $sql    = $this->compileSelect($this->select, true, $params);
        $res    = $this->db->query($sql, $params);
        $this->reset();
        return $res;
    }

    public function countAllResults(bool $reset = true): int
    {
        $params = [];
        if ($this->groupBy) {
            $inner = $this->compileSelect($this->select, false, $params);
            $sql   = 'SELECT COUNT(*) AS n FROM (' . $inner . ') AS grouped_count';
        } else {
            $sql = $this->compileSelect('COUNT(*) AS n', false, $params);
        }
        $row = $this->db->query($sql, $params)->getRowArray();
        if ($reset) {
            $this->reset();
        }
        return (int) ($row['n'] ?? 0);
    }

    public function insert(array $data): int
    {
        $cols  = array_keys($data);
        $marks = implode(', ', array_fill(0, count($cols), '?'));
        $sql   = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . $marks . ')';
        $this->db->query($sql, array_values($this->normalize($data)));
        $this->reset();
        return $this->db->insertID();
    }

    public function insertBatch(array $rows): int
    {
        if (! $rows) {
            return 0;
        }
        $cols   = array_keys($rows[0]);
        $marks  = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $params = [];
        foreach ($rows as $row) {
            foreach ($cols as $c) {
                $params[] = $this->normalizeValue($row[$c] ?? null);
            }
        }
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES '
             . implode(', ', array_fill(0, count($rows), $marks));
        $this->db->query($sql, $params);
        $this->reset();
        return count($rows);
    }

    public function update(array $data = null): bool
    {
        if ($data !== null) {
            $this->set($data);
        }
        if (! $this->sets) {
            $this->reset();
            return false;
        }
        $params = $this->setParams;
        $sql    = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $this->sets) . $this->compileWhere($params);
        $this->db->query($sql, $params);
        $this->reset();
        return true;
    }

    public function delete(): bool
    {
        $params = [];
        $where  = $this->compileWhere($params);
        $sql    = 'DELETE FROM ' . $this->table . $where;
        $this->db->query($sql, $params);
        $this->reset();
        return true;
    }

    private function normalize(array $data): array
    {
        foreach ($data as $k => $v) {
            $data[$k] = $this->normalizeValue($v);
        }
        return $data;
    }

    private function normalizeValue($v)
    {
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_array($v)) {
            return json_encode($v);
        }
        return $v;
    }

    public function reset(): void
    {
        $this->select    = '*';
        $this->distinct  = false;
        $this->joins     = [];
        $this->wheres    = [];
        $this->groupBy   = [];
        $this->orderBy   = [];
        $this->limit     = null;
        $this->offset    = 0;
        $this->sets      = [];
        $this->setParams = [];
    }
}

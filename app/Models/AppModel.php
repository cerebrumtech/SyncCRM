<?php

namespace App\Models;

use Sync\Model;

/** Common settings: array rows, datetime timestamps, no validation magic. */
abstract class AppModel extends Model
{
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useAutoIncrement = true;

    /** Fetch one row scoped to an organisation (or null). */
    public function findInOrg(int $orgId, int $id): ?array
    {
        return $this->where('organization_id', $orgId)->find($id);
    }
}

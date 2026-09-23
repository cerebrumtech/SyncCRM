<?php

namespace App\Models;

class SavedViewModel extends AppModel
{
    protected $table         = 'saved_views';
    protected $allowedFields = ['organization_id', 'entity', 'name', 'filters', 'owner_id', 'is_shared', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'filters' => '?json-array',
        'is_shared' => 'int-bool',
        'owner_id' => 'int',
        'organization_id' => 'int',
    ];
}

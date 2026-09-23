<?php

namespace App\Models;

class TeamModel extends AppModel
{
    protected $table         = 'teams';
    protected $allowedFields = ['organization_id', 'name', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'organization_id' => 'int',
    ];
}

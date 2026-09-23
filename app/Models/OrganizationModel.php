<?php

namespace App\Models;

class OrganizationModel extends AppModel
{
    protected $table         = 'organizations';
    protected $allowedFields = ['name', 'timezone', 'currency', 'settings', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'settings' => '?json-array',
    ];
}

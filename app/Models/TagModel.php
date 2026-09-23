<?php

namespace App\Models;

class TagModel extends AppModel
{
    protected $table         = 'tags';
    protected $allowedFields = ['organization_id', 'name', 'color'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
    ];
}

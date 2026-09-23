<?php

namespace App\Models;

class PipelineModel extends AppModel
{
    protected $table         = 'pipelines';
    protected $allowedFields = ['organization_id', 'name', 'position', 'is_default', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'is_default' => 'int-bool',
        'position' => 'int',
        'organization_id' => 'int',
    ];
}

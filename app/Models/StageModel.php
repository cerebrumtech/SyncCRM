<?php

namespace App\Models;

class StageModel extends AppModel
{
    protected $table         = 'stages';
    protected $allowedFields = ['pipeline_id', 'name', 'position', 'probability', 'is_won', 'is_lost', 'required_fields', 'color'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
        'is_won' => 'int-bool',
        'is_lost' => 'int-bool',
        'required_fields' => '?json-array',
        'position' => 'int',
        'probability' => 'int',
        'pipeline_id' => 'int',
    ];
}

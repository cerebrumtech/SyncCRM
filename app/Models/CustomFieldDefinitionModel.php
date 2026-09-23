<?php

namespace App\Models;

class CustomFieldDefinitionModel extends AppModel
{
    protected $table         = 'custom_field_definitions';
    protected $allowedFields = ['organization_id', 'entity', 'field_key', 'label', 'type', 'options', 'required', 'position'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
        'options' => '?json-array',
        'required' => 'int-bool',
        'position' => 'int',
        'organization_id' => 'int',
    ];
}

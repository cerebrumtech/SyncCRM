<?php

namespace App\Models;

class CompanyModel extends AppModel
{
    protected $table         = 'companies';
    protected $allowedFields = ['organization_id', 'name', 'industry', 'website', 'phone', 'email', 'address_line', 'city', 'state', 'postal_code', 'country', 'description', 'tags', 'custom_fields', 'owner_id', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'tags' => '?json-array',
        'custom_fields' => '?json-array',
        'organization_id' => 'int',
        'owner_id' => '?int',
    ];
}

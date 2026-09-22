<?php

namespace App\Models;

class ContactModel extends AppModel
{
    protected $table         = 'contacts';
    protected $allowedFields = ['organization_id', 'first_name', 'last_name', 'email', 'phone', 'phone_normalized', 'whatsapp_number', 'job_title', 'company_id', 'tags', 'custom_fields', 'owner_id', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'tags' => '?json-array',
        'custom_fields' => '?json-array',
        'organization_id' => 'int',
        'owner_id' => '?int',
        'company_id' => '?int',
    ];
}

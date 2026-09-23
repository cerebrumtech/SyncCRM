<?php

namespace App\Models;

class AttachmentModel extends AppModel
{
    protected $table         = 'attachments';
    protected $allowedFields = ['organization_id', 'filename', 'storage_path', 'mime_type', 'size', 'contact_id', 'company_id', 'deal_id', 'uploaded_by_id', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'organization_id' => 'int',
        'size' => 'int',
        'contact_id' => '?int',
        'company_id' => '?int',
        'deal_id' => '?int',
    ];
}

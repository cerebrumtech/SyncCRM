<?php

namespace App\Models;

class NoteModel extends AppModel
{
    protected $table         = 'notes';
    protected $allowedFields = ['organization_id', 'body', 'contact_id', 'company_id', 'deal_id', 'author_id', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'organization_id' => 'int',
        'author_id' => 'int',
        'contact_id' => '?int',
        'company_id' => '?int',
        'deal_id' => '?int',
    ];
}

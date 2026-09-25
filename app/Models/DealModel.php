<?php

namespace App\Models;

class DealModel extends AppModel
{
    protected $table         = 'deals';
    protected $allowedFields = ['organization_id', 'title', 'pipeline_id', 'stage_id', 'status', 'amount', 'amount_is_manual', 'proposal_amount', 'amount_received', 'amount_pending', 'lead_source', 'expected_close_date', 'closed_at', 'lost_reason', 'contact_id', 'company_id', 'owner_id', 'position', 'tags', 'custom_fields', 'source_deal_id', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'tags' => '?json-array',
        'custom_fields' => '?json-array',
        'amount_is_manual' => 'int-bool',
        'amount' => 'float',
        'position' => 'int',
        'organization_id' => 'int',
        'pipeline_id' => 'int',
        'stage_id' => 'int',
        'owner_id' => '?int',
        'contact_id' => '?int',
        'company_id' => '?int',
        'source_deal_id' => '?int',
    ];
}

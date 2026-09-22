<?php

namespace App\Libraries;

use App\Models\PipelineModel;
use App\Models\StageModel;

class Defaults
{
    public const PIPELINES = [
        ['name' => 'Sales', 'isDefault' => true, 'stages' => [
            ['name' => 'New Lead', 'probability' => 10, 'color' => '#04A2FB'],
            ['name' => 'Contacted', 'probability' => 20, 'color' => '#04A2FB'],
            ['name' => 'Qualified', 'probability' => 40, 'color' => '#0068FF'],
            ['name' => 'Proposal Sent', 'probability' => 60, 'color' => '#0068FF'],
            ['name' => 'Negotiation', 'probability' => 80, 'color' => '#1B243E'],
            ['name' => 'Won', 'probability' => 100, 'isWon' => true, 'color' => '#10B981'],
            ['name' => 'Lost', 'probability' => 0, 'isLost' => true, 'color' => '#EF4444'],
        ]],
        ['name' => 'Onboarding', 'stages' => [
            ['name' => 'Kickoff', 'probability' => 20, 'color' => '#04A2FB'],
            ['name' => 'Data Migration', 'probability' => 50, 'color' => '#0068FF'],
            ['name' => 'Training', 'probability' => 80, 'color' => '#1B243E'],
            ['name' => 'Live', 'probability' => 100, 'isWon' => true, 'color' => '#10B981'],
            ['name' => 'Dropped', 'probability' => 0, 'isLost' => true, 'color' => '#EF4444'],
        ]],
    ];

    public const LOST_REASONS = ['Price too high', 'Chose competitor', 'No budget', 'No response', 'Timing not right', 'Not a fit', 'Other'];

    public const DEDUPE = ['contactEmail' => 'warn', 'contactPhone' => 'warn', 'companyName' => 'warn'];

    /** Creates the standard Sales + Onboarding pipelines for a new organisation. */
    public static function createPipelines(int $orgId): void
    {
        $pipelines = model(PipelineModel::class);
        $stages = model(StageModel::class);
        foreach (self::PIPELINES as $i => $p) {
            $pid = $pipelines->insert(['organization_id' => $orgId, 'name' => $p['name'], 'position' => $i, 'is_default' => ! empty($p['isDefault'])]);
            foreach ($p['stages'] as $j => $s) {
                $stages->insert(['pipeline_id' => $pid, 'name' => $s['name'], 'position' => $j, 'probability' => $s['probability'], 'is_won' => ! empty($s['isWon']), 'is_lost' => ! empty($s['isLost']), 'required_fields' => [], 'color' => $s['color'] ?? '#0068FF']);
            }
        }
    }
}

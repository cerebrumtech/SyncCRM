<?php

namespace App\Libraries;

use App\Exceptions\FormError;
use App\Models\DealLineItemModel;
use App\Models\DealModel;
use App\Models\PipelineModel;
use App\Models\StageModel;

class Deals
{
    public const REQUIRED_OPTIONS = ['amount' => 'Amount', 'expected_close_date' => 'Expected close date', 'contact_id' => 'Contact', 'company_id' => 'Company', 'owner_id' => 'Owner'];

    public static function pipelines(int $orgId): array
    {
        $pipelines = model(PipelineModel::class)->where('organization_id', $orgId)->orderBy('position')->orderBy('id')->findAll();
        if ($pipelines) {
            $stages = model(StageModel::class)->whereIn('pipeline_id', array_column($pipelines, 'id'))->orderBy('position')->orderBy('id')->findAll();
            foreach ($pipelines as &$p) {
                $p['stages'] = array_values(array_filter($stages, fn ($s) => $s['pipeline_id'] === $p['id']));
            }
        }
        return $pipelines;
    }

    public static function statusFor(array $stage): string
    {
        return $stage['is_won'] ? 'WON' : ($stage['is_lost'] ? 'LOST' : 'OPEN');
    }

    /** Human labels of the stage's required fields the deal has not filled in. */
    public static function missingFields(array $stage, array $deal, array $defs = []): array
    {
        $labels = self::REQUIRED_OPTIONS;
        foreach ($defs as $d) {
            $labels['cf_' . $d['field_key']] = $d['label'];
        }
        $cf = $deal['custom_fields'] ?? [];
        $missing = [];
        foreach ($stage['required_fields'] ?? [] as $f) {
            if ($f === 'amount') {
                $empty = (float) ($deal['amount'] ?? 0) <= 0;
            } elseif (isset(self::REQUIRED_OPTIONS[$f])) {
                $empty = empty($deal[$f]);
            } else {
                $v = $cf[substr($f, 3)] ?? null;
                $empty = $v === null || $v === '' || $v === false;
            }
            if ($empty) {
                $missing[] = $labels[$f] ?? substr($f, 3);
            }
        }
        return $missing;
    }

    public static function lineTotal(float $qty, float $price, float $discount, float $tax): float
    {
        $net = $qty * $price * (1 - $discount / 100);
        return round($net * (1 + $tax / 100), 2);
    }

    /** Inserts the deal at $position within the target stage and renumbers siblings; updates status fields. */
    public static function place(array $deal, array $stage, ?int $position, ?string $lostReason = null): void
    {
        $m = model(DealModel::class);
        $status = self::statusFor($stage);
        $db = db_connect();
        $db->transStart();
        $siblings = array_column($m->select('id')->where('stage_id', $stage['id'])->where('id !=', $deal['id'])->orderBy('position')->orderBy('id')->findAll(), 'id');
        $idx = $position === null || $position > count($siblings) ? count($siblings) : max(0, $position);
        array_splice($siblings, $idx, 0, [$deal['id']]);
        foreach ($siblings as $i => $sid) {
            if ($sid === $deal['id']) {
                $m->update($sid, ['position' => $i, 'stage_id' => $stage['id'], 'status' => $status,
                    'closed_at' => $status === 'OPEN' ? null : ($deal['closed_at'] ?? now_sql()),
                    'lost_reason' => $status === 'LOST' ? ($lostReason ?? $deal['lost_reason']) : null]);
            } else {
                $m->update($sid, ['position' => $i]);
            }
        }
        $db->transComplete();
    }

    public static function nextPosition(int $stageId): int
    {
        $last = model(DealModel::class)->select('position')->where('stage_id', $stageId)->orderBy('position', 'DESC')->first();
        return $last ? (int) $last['position'] + 1 : 0;
    }

    public static function lineItems(int $dealId): array
    {
        return model(DealLineItemModel::class)->where('deal_id', $dealId)->orderBy('position')->orderBy('id')->findAll();
    }
}

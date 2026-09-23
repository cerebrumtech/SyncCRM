<?php

namespace App\Libraries;

use App\Models\TagModel;

class Tags
{
    /** "vip, Hot lead,, vip" -> ['vip', 'hot-lead'] */
    public static function parse(?string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,\n]+/', (string) $raw) as $t) {
            $t = strtolower(trim($t));
            $t = preg_replace('/\s+/', '-', $t);
            $t = preg_replace('/[^a-z0-9\-_]/', '', $t);
            if ($t !== '' && ! in_array($t, $out, true) && count($out) < 20) {
                $out[] = mb_substr($t, 0, 40);
            }
        }
        return $out;
    }

    public static function ensure(int $orgId, array $names): void
    {
        if (! $names) {
            return;
        }
        $m = model(TagModel::class);
        $existing = array_column($m->where('organization_id', $orgId)->whereIn('name', $names)->findAll(), 'name');
        foreach (array_diff($names, $existing) as $n) {
            $m->insert(['organization_id' => $orgId, 'name' => $n]);
        }
    }

    public static function names(int $orgId): array
    {
        return array_column(model(TagModel::class)->where('organization_id', $orgId)->orderBy('name')->findAll(), 'name');
    }
}

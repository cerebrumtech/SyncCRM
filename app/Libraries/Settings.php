<?php

namespace App\Libraries;

use App\Models\OrganizationModel;

class Settings
{
    public static function all(int $orgId): array
    {
        $org = model(OrganizationModel::class)->find($orgId);
        return $org['settings'] ?? [];
    }

    public static function dedupe(int $orgId): array
    {
        return array_merge(Defaults::DEDUPE, self::all($orgId)['dedupe'] ?? []);
    }

    public static function set(int $orgId, string $key, $value): void
    {
        $settings = self::all($orgId);
        $settings[$key] = $value;
        model(OrganizationModel::class)->update($orgId, ['settings' => $settings]);
    }
}

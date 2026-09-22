<?php

namespace App\Libraries;

use App\Exceptions\FormError;
use App\Models\CustomFieldDefinitionModel;

class CustomFields
{
    public const TYPES = ['TEXT', 'NUMBER', 'DATE', 'SELECT', 'CHECKBOX'];

    public static function defs(int $orgId, string $entity): array
    {
        return model(CustomFieldDefinitionModel::class)->where('organization_id', $orgId)->where('entity', $entity)->orderBy('position')->orderBy('id')->findAll();
    }

    /** Reads cf_<key> inputs and validates them against the definitions. */
    public static function parse(array $defs, array $post): array
    {
        $out = [];
        foreach ($defs as $def) {
            $raw = $post['cf_' . $def['field_key']] ?? null;
            $value = is_string($raw) ? trim($raw) : '';
            if ($def['type'] === 'CHECKBOX') {
                $out[$def['field_key']] = $raw === 'on' || $raw === '1' || $raw === 'true';
                continue;
            }
            if ($value === '') {
                if ($def['required']) {
                    throw new FormError($def['label'] . ' is required.');
                }
                $out[$def['field_key']] = null;
                continue;
            }
            switch ($def['type']) {
                case 'NUMBER':
                    if (! is_numeric($value)) {
                        throw new FormError($def['label'] . ' must be a number.');
                    }
                    $out[$def['field_key']] = $value + 0;
                    break;
                case 'SELECT':
                    if (! in_array($value, $def['options'] ?? [], true)) {
                        throw new FormError($def['label'] . ' has an invalid option.');
                    }
                    $out[$def['field_key']] = $value;
                    break;
                default:
                    $out[$def['field_key']] = $value;
            }
        }
        return $out;
    }

    public static function display(array $def, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return match ($def['type']) {
            'CHECKBOX' => $value ? 'Yes' : 'No',
            'DATE'     => format_date($value),
            'NUMBER'   => number_format((float) $value, fmod((float) $value, 1.0) == 0 ? 0 : 2),
            default    => (string) $value,
        };
    }
}

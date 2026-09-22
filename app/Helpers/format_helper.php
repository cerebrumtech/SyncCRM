<?php

// Indian formatting: ₹ with lakh/crore grouping, DD/MM/YYYY dates, IST wall-clock (app timezone).

function format_inr(mixed $value, bool $precise = false): string
{
    $n = is_numeric($value) ? (float) $value : 0.0;
    $neg = $n < 0;
    $n = abs($n);
    $whole = (string) floor($n);
    $dec = $precise ? '.' . str_pad((string) round(($n - floor($n)) * 100), 2, '0', STR_PAD_LEFT) : '';
    if (! $precise) {
        $whole = (string) round($n);
    }
    if (strlen($whole) > 3) {
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $whole = $rest . ',' . $last3;
    }
    return ($neg ? '-' : '') . '₹' . $whole . $dec;
}

function format_compact_inr(mixed $value): string
{
    $n = is_numeric($value) ? (float) $value : 0.0;
    $abs = abs($n);
    if ($abs >= 10000000) {
        return '₹' . number_format($n / 10000000, 2) . ' Cr';
    }
    if ($abs >= 100000) {
        return '₹' . number_format($n / 100000, 2) . ' L';
    }
    return format_inr($n);
}

function to_ts(mixed $d): ?int
{
    if ($d === null || $d === '' || $d === '0000-00-00 00:00:00') {
        return null;
    }
    if ($d instanceof DateTimeInterface) {
        return $d->getTimestamp();
    }
    if (is_int($d)) {
        return $d;
    }
    $ts = strtotime((string) $d);
    return $ts === false ? null : $ts;
}

function format_date(mixed $d): string
{
    $ts = to_ts($d);
    return $ts === null ? '—' : date('d/m/Y', $ts);
}

function format_datetime(mixed $d): string
{
    $ts = to_ts($d);
    return $ts === null ? '—' : date('d/m/Y, h:i a', $ts);
}

function format_time(mixed $d): string
{
    $ts = to_ts($d);
    return $ts === null ? '' : date('h:i a', $ts);
}

function relative_time(mixed $d): string
{
    $ts = to_ts($d);
    if ($ts === null) {
        return '—';
    }
    $mins = (int) round((time() - $ts) / 60);
    if (abs($mins) < 1) {
        return 'just now';
    }
    if (abs($mins) < 60) {
        return $mins > 0 ? "{$mins}m ago" : 'in ' . (-$mins) . 'm';
    }
    $hours = (int) round($mins / 60);
    if (abs($hours) < 24) {
        return $hours > 0 ? "{$hours}h ago" : 'in ' . (-$hours) . 'h';
    }
    $days = (int) round($hours / 24);
    if (abs($days) < 30) {
        return $days > 0 ? "{$days}d ago" : 'in ' . (-$days) . 'd';
    }
    return format_date($ts);
}

/** Strips everything but digits; Indian 10-digit numbers get the 91 country code. */
function normalize_phone(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = '91' . substr($digits, 1);
    }
    return $digits;
}

function initials(string $name): string
{
    $parts = array_values(array_filter(preg_split('/\s+/', trim($name))));
    return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
}

function full_name(array $c): string
{
    return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

/** Value for <input type="date"> */
function date_input(mixed $d): string
{
    $ts = to_ts($d);
    return $ts === null ? '' : date('Y-m-d', $ts);
}

/** Value for <input type="datetime-local"> */
function datetime_input(mixed $d): string
{
    $ts = to_ts($d);
    return $ts === null ? '' : date('Y-m-d\TH:i', $ts);
}

/** Parses a date or datetime-local input into a MySQL datetime string (or null). */
function parse_datetime(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/', $v, $m)) {
        $h = $m[4] ?? '00';
        $i = $m[5] ?? '00';
        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return "{$m[1]}-{$m[2]}-{$m[3]} {$h}:{$i}:00";
    }
    return null;
}

function parse_date(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    return null;
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

function money(mixed $v): float
{
    return is_numeric($v) ? round((float) $v, 2) : 0.0;
}

function file_size_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
}

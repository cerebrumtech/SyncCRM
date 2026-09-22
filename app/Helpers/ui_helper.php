<?php

/** Small view helpers producing brand-styled HTML fragments. */

function avatar(string $name, string $color = '#0068FF', int $size = 32): string
{
    $font = max(10, (int) round($size * 0.38));
    return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;background:' . esc($color, 'attr') . ';font-size:' . $font . 'px" title="' . esc($name, 'attr') . '">' . esc(initials($name)) . '</span>';
}

function badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge-' . esc($tone, 'attr') . '">' . esc($text) . '</span>';
}

function tag_badges(?array $tags): string
{
    if (! $tags) {
        return '';
    }
    return implode(' ', array_map(fn ($t) => '<span class="tag">' . esc($t) . '</span>', $tags));
}

function deal_status_badge(string $status): string
{
    return badge(ucfirst(strtolower($status)), match ($status) { 'WON' => 'success', 'LOST' => 'danger', default => 'info' });
}

function activity_status_badge(string $status): string
{
    return badge(ucfirst(strtolower($status)), match ($status) { 'COMPLETED' => 'success', 'CANCELLED' => 'neutral', default => 'warning' });
}

function activity_type_label(string $type): string
{
    return match ($type) { 'CALL' => 'Call', 'EVENT' => 'Meeting', default => 'Task' };
}

function role_label(string $role): string
{
    return ucfirst(strtolower($role));
}

/** Inline SVG icons (Lucide outlines), keyed by name. */
function icon(string $name, string $class = 'h-4 w-4'): string
{
    $paths = [
        'dashboard'  => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'deals'      => '<path d="M6 5v11"/><path d="M12 5v6"/><path d="M18 5v14"/><rect width="18" height="18" x="3" y="3" rx="2"/>',
        'contacts'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'companies'  => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
        'activities' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>',
        'products'   => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'settings'   => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'plus'       => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'x'          => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check'      => '<path d="M20 6 9 17l-5-5"/>',
        'phone'      => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail'       => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'calendar'   => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'file'       => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        'note'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/>',
        'arrow-right'=> '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'upload'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'menu'       => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'repeat'     => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
        'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'share'      => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98"/><path d="m15.41 6.51-6.82 3.98"/>',
        'merge'      => '<path d="m6 7 6 6 6-6"/><path d="M12 13v8"/>',
        'link'       => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'filter'     => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'columns'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="M15 3v18"/>',
        'list'       => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        'alert'      => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    ];
    $d = $paths[$name] ?? $paths['alert'];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . esc($class, 'attr') . '" aria-hidden="true">' . $d . '</svg>';
}

/** Builds a query string from current GET params with overrides ('' removes a key). */
function query_with(array $overrides): string
{
    $params = array_merge(service('request')->getGet(), $overrides);
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
}

function selected_if(bool $cond): string
{
    return $cond ? ' selected' : '';
}

function checked_if(bool $cond): string
{
    return $cond ? ' checked' : '';
}

function old_or(string $key, mixed $default = ''): string
{
    $v = old($key);
    return esc($v !== null ? $v : (string) ($default ?? ''));
}

/** Sort-link header cell that toggles direction. */
function sort_link(string $label, string $key): string
{
    $sort = service('request')->getGet('sort') ?? '';
    $dir = service('request')->getGet('dir') ?? 'asc';
    $active = $sort === $key;
    $next = $active && $dir === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
    return '<a href="' . esc(query_with(['sort' => $key, 'dir' => $next, 'page' => '']), 'attr') . '" class="hover:text-navy">' . esc($label) . $arrow . '</a>';
}

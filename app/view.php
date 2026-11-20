<?php
declare(strict_types=1);

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminUrl(string $view = 'dashboard', int $id = 0): string
{
    return './' . ($view === 'dashboard' ? '' : '?view=' . $view . ($id ? '&id=' . $id : ''));
}

function articleUrl(string $slug, string $root = './'): string
{
    return $root . '?article=' . rawurlencode($slug);
}

function dateLabel(int $timestamp): string
{
    $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

function csrfField(): void
{
    echo '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function statusBadge(bool $active, string $positive, string $negative): void
{
    echo '<span class="status ' . ($active ? 'status-live' : 'status-muted') . '">' . e($active ? $positive : $negative) . '</span>';
}

function icon(string $name, string $class = ''): void
{
    $shapes = [
        'plugy' => '<path d="M8 8h8a3 3 0 0 1 0 6h-5m5 2H8a3 3 0 0 1 0-6h5m-3 3 4-2"/>',
        'grid' => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1"/><rect x="14" y="14" width="6.5" height="6.5" rx="1"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/>',
        'file' => '<path d="M7 3.5h7l4.5 4.5v11A1.5 1.5 0 0 1 17 20.5H7A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5Zm7 0V9h4.5M9 13h6m-6 3.5h6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m10 3-.6 2.4-2 .9-2.2-1.2-2.1 3.7 1.9 1.7v2.3l-1.9 1.7 2.1 3.7 2.3-1.1 1.9 1 .6 2.4h4l.6-2.4 2-.9 2.2 1.2 2.1-3.7-1.9-1.7v-2.3l1.9-1.7-2.1-3.7-2.3 1.1-1.9-1L14 3h-4Z"/>',
        'external' => '<path d="M13 5h6v6m0-6-8 8m-2-7H6a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-3"/>',
        'logout' => '<path d="M10 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4m3-3 4-4-4-4m-4 4h11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
        'eye-off' => '<path d="m4 4 16 16M10 6.2a10 10 0 0 1 2-.2c6 0 9.5 6 9.5 6s-1.1 1.9-3.1 3.5M6.2 7.8A16 16 0 0 0 2.5 12s3.5 6 9.5 6a10 10 0 0 0 3.8-.8"/>',
        'lock' => '<rect x="5.5" y="10" width="13" height="10" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20v-1.5a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4V20"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><ellipse cx="12" cy="12" rx="3.5" ry="8.5"/><path d="M3.5 12h17"/>',
        'up' => '<path d="m6 14 6-6 6 6"/>',
        'down' => '<path d="m6 10 6 6 6-6"/>',
        'chevron' => '<path d="m10 6 6 6-6 6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'trash' => '<path d="M4 7h16M9 7V4.5h6V7m-9 0 1 13h10l1-13m-8 4v5m4-5v5"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l2.5 1.5"/>',
        'edit' => '<path d="m14.5 5.5 4 4M5 15l10-10a2.8 2.8 0 0 1 4 4L9 19l-4 1 0-5Z"/>',
        'shield' => '<path d="M12 3.5 19 7v5c0 4-2.5 6.5-7 8.5C7.5 18.5 5 16 5 12V7l7-3.5Zm-3 8.5 2 2 4-4"/>',
    ];
    echo '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $shapes[$name] . '</svg>';
}

function brand(): void
{
    echo '<span class="brand-mark">';
    icon('plugy');
    echo '</span><span class="brand-name">Plugy <span>Engine</span></span>';
}

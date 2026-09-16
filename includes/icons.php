<?php
/**
 * Minimal inline SVG icon set. Usage: <?= icon('dashboard') ?>
 * All icons are 20x20, stroke-based, and inherit color via currentColor
 * so they pick up sidebar/topbar text colors automatically.
 */
function icon($name, $size = 20) {
    $paths = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'inventory'  => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
        'stock'      => '<path d="M7 17l-4-4 4-4"/><path d="M3 13h11a4 4 0 0 0 0-8h-1"/><path d="M17 7l4 4-4 4"/><path d="M21 11H10a4 4 0 0 0 0 8h1"/>',
        'transfer'   => '<rect x="1" y="6" width="9" height="7" rx="1"/><rect x="14" y="11" width="9" height="7" rx="1"/><path d="M10 9.5h5a2 2 0 0 1 2 2v-.5"/><path d="M14 14.5H9a2 2 0 0 1-2-2V13"/><path d="M15 8l2 1.5-2 1.5"/><path d="M9 16l-2-1.5L9 13"/>',
        'orders'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.6a2 2 0 0 0 2-1.6L21 8H6"/>',
        'purchasing' => '<rect x="2" y="8" width="13" height="9" rx="1.5"/><path d="M15 11h3l3 3v3h-6"/><circle cx="7" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
        'customers'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 19a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 6.6"/><path d="M18 13a6.5 6.5 0 0 1 4 6"/>',
        'suppliers'  => '<path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-6h6v6"/><path d="M3 9h18"/>',
        'reports'    => '<path d="M4 20V10"/><path d="M11 20V4"/><path d="M18 20v-7"/><path d="M2 20h20"/>',
        'categories' => '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
        'activity'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'users'      => '<circle cx="9" cy="7" r="3.5"/><path d="M2 19a7 7 0 0 1 14 0"/><path d="M16.5 4.5a3.5 3.5 0 0 1 0 6.5"/><path d="M19 13.5a6 6 0 0 1 3.5 5.5"/>',
        'search'     => '<circle cx="10" cy="10" r="6.5"/><path d="M19 19l-4.3-4.3"/>',
        'bell'       => '<path d="M5 9a7 7 0 0 1 14 0c0 5 2 6 2 6H3s2-1 2-6z"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0"/>',
        'chevron'    => '<path d="M5 8l7 7 7-7"/>',
        'menu'       => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'print'      => '<path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><path d="M6 17v4h12v-4"/>',
        'download'   => '<path d="M12 3v13"/><path d="M6 11l6 6 6-6"/><path d="M4 20h16"/>',
        'close'      => '<path d="M5 5l14 14"/><path d="M19 5L5 19"/>',
        'check'      => '<path d="M4 12l5 5L20 6"/>',
        'warning'    => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v4"/><path d="M12 17.5v.01"/>',
        'trend'      => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.6 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1z"/>',
        'box-check'  => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M9 12l2 2 4-4"/>',
        'arrow-right'=> '<path d="M5 12h14"/><path d="M13 5l7 7-7 7"/>',
    ];
    $path = $paths[$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="icon icon-' . htmlspecialchars($name) . '">' . $path . '</svg>';
}

{{-- Ikon SVG inline pengganti emoji (tajam di semua browser/HP).
  Pakai: <x-icon name="fork" /> — ukuran mengikuti font-size induk. --}}
@props(['name' => 'info', 'class' => ''])
@php
$paths = [
    'fork' => '<path d="M4 2v7c0 1.1.9 2 2 2h2a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M20 15V2a4 4 0 0 0-4 4v6c0 1.1.9 2 2 2h2Zm0 0v7"/>',
    'sparkle' => '<path fill="currentColor" stroke="none" d="M12 2c.7 5.6 4.4 9.3 10 10-5.6.7-9.3 4.4-10 10-.7-5.6-4.4-9.3-10-10 5.6-.7 9.3-4.4 10-10Z"/>',
    'check' => '<path d="M20 6 9 17l-5-5"/>',
    'check-box' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="m9 12 2 2 4-4"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
    'grid' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
    'history' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    'trend-down' => '<path d="m22 17-8.5-8.5-5 5L2 7"/><path d="M16 17h6v-6"/>',
    'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M16 2v4M8 2v4M3 9.5h18"/>',
    'print' => '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>',
    'sunrise' => '<path d="M12 9V3"/><path d="m10 5 2-2 2 2"/><path d="M17 18a5 5 0 0 0-10 0"/><path d="M2 18h20"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
    'fire' => '<path d="M12 22c4 0 7-2.9 7-7 0-3.5-2.5-6-5-8-.8 1.7-1.5 3-1.5 4.5-1.5-.5-2.5-2-2.5-4C7.5 8 5 10.5 5 15c0 4.1 3 7 7 7Z"/>',
    'timer' => '<circle cx="12" cy="13" r="8"/><path d="M12 9.5V13l2.5 2.5M9 2h6"/>',
    'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/><path d="M15 7h4v4"/>',
    'bars' => '<path d="M3 3v18h18"/><path d="M8 17v-5m5 5V8m5 9v-3"/>',
    'smile' => '<circle cx="12" cy="12" r="9"/><path d="M8 14.5s1.5 2 4 2 4-2 4-2"/><path d="M9 9.5h.01M15 9.5h.01"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4.5M12 7.5h.01"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/>',
    'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
    'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    'sprout' => '<path d="M7 20h10"/><path d="M12 20v-9"/><path d="M12 11C12 7 9 4 4 4c0 5 3 8 8 7Z"/><path d="M12 11c0-3 2.5-5 7-5 0 4-2.5 6-7 5Z"/>',
    'broccoli' => '<circle cx="9" cy="9" r="3.5"/><circle cx="15" cy="9" r="3.5"/><circle cx="12" cy="6.5" r="3.5"/><path d="M12 10v4m0 0-2 7m2-7 2 7"/>',
    'meat' => '<ellipse cx="10" cy="14" rx="6" ry="5" transform="rotate(-30 10 14)"/><path d="m14.5 9.5 4-4"/><circle cx="20" cy="4" r="1.6"/><circle cx="17" cy="7" r="1.6"/>',
    'leafy' => '<path d="M5 19C5 10 11 5 20 4c-1 9-7 15-15 15Z"/><path d="M5 19c3-5 7-9 11-11"/>',
    'memo' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9l-5-7Z"/><path d="M14 3v6h5"/><path d="M9 13h6M9 17h4"/>',
    'warn' => '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 10v4.5M12 17.5h.01"/>',
    'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'cake' => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M2 21h20"/><path d="M12 11V7"/><path d="M12 7a1.5 1.5 0 1 0 .01 0"/>',
    'scale' => '<path d="M12 3v18M7 21h10"/><path d="M12 5 5 7m7-2 7 2"/><path d="M5 7l-2.5 6a3 3 0 0 0 5 0L5 7Zm14 0-2.5 6a3 3 0 0 0 5 0L19 7Z"/>',
    'ruler' => '<path d="m21.3 8.7-12.6 12.6a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"/><path d="m7.5 10.5 2 2m1.5-5.5 2 2m1.5-5.5 2 2"/>',
];
echo '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:1em;height:1em;vertical-align:-.125em;flex:none;">'.($paths[$name] ?? $paths['info']).'</svg>';
@endphp

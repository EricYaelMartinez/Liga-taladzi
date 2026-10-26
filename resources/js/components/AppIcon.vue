<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ name: string; size?: number | string; strokeWidth?: number; variant?: 'line' | 'illustrated' }>(), {
    size: 18,
    strokeWidth: 1.8,
    variant: 'line',
});

const greenIcons = new Set(['users', 'team', 'football', 'field', 'check', 'refresh', 'shield']);
const goldIcons = new Set(['settings', 'trophy', 'bell', 'badge', 'id', 'clipboard', 'gavel', 'wallet']);
const iconPalette = computed(() => {
    if (greenIcons.has(props.name)) return { accent: '#2E7D32', soft: '#E8F4EA', secondary: '#C5A059' };
    if (goldIcons.has(props.name)) return { accent: '#C5A059', soft: '#F8F1DF', secondary: '#2E7D32' };
    return { accent: '#315E91', soft: '#EAF1F8', secondary: '#C5A059' };
});

const icons: Record<string, string[]> = {
    home: ['M3 11.5 12 4l9 7.5', 'M5 10.5V20h14v-9.5', 'M9 20v-6h6v6'],
    shield: ['M12 3 4.5 6v5.5c0 4.7 3.1 7.8 7.5 9.5 4.4-1.7 7.5-4.8 7.5-9.5V6L12 3Z', 'm9 12 2 2 4-4'],
    settings: ['M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z', 'M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-1.8-.2 8 8 0 0 1-1.7 1v.2a1.7 1.7 0 0 0-1.5 1.2h-4a1.7 1.7 0 0 0-1.5-1.2V21a8 8 0 0 1-1.7-1 1.7 1.7 0 0 0-1.8.2l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.9 8 8 0 0 1 0-2v-.1a1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 1.8.2 8 8 0 0 1 1.7-1v-.2A1.7 1.7 0 0 0 8.6 5h4a1.7 1.7 0 0 0 1.5 1.2v.2a8 8 0 0 1 1.7 1 1.7 1.7 0 0 0 1.8-.2l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a8 8 0 0 1 0 2Z'],
    users: ['M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-6A3.5 3.5 0 0 0 3 18.5V20', 'M9.5 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M16 4.7a3.5 3.5 0 0 1 0 6.6', 'M18 15.2a3.5 3.5 0 0 1 3 3.3V20'],
    calendar: ['M5 3v3M19 3v3M4 8h16', 'M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z', 'M7 12h3M14 12h3M7 16h3M14 16h3'],
    football: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', 'm12 8 3 2.2-1.1 3.5h-3.8L9 10.2 12 8Z', 'M12 3v5M20.6 9.2 15 10.2M17.3 19.2l-3.4-5.5M6.7 19.2l3.4-5.5M3.4 9.2 9 10.2'],
    clipboard: ['M9 4h6l1 2h3v15H5V6h3l1-2Z', 'M9 4v3h6V4', 'M8 12h8M8 16h6'],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
    building: ['M4 21V5l8-2 8 2v16', 'M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2', 'M3 21h18'],
    database: ['M4 6c0 1.7 3.6 3 8 3s8-1.3 8-3-3.6-3-8-3-8 1.3-8 3Z', 'M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6', 'M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6'],
    palette: ['M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 0 0-4H12a2 2 0 0 1 0-4h4.5A4.5 4.5 0 0 0 21 8.5C21 5.5 17 3 12 3Z', 'M7.5 9h.01M9.5 6h.01M14 6h.01M17 9h.01'],
    sliders: ['M4 6h6M14 6h6M10 3v6', 'M4 12h10M18 12h2M14 9v6', 'M4 18h3M11 18h9M7 15v6'],
    trophy: ['M8 4h8v3c0 4-1.8 7-4 7S8 11 8 7V4Z', 'M8 6H4c0 3 1.5 5 4.5 5M16 6h4c0 3-1.5 5-4.5 5', 'M12 14v4M8 21h8M9 18h6'],
    team: ['M12 3 5 6v5c0 4.5 2.8 7.6 7 9.5 4.2-1.9 7-5 7-9.5V6l-7-3Z', 'M9 12h6M12 9v6'],
    field: ['M3 5h18v14H3V5Z', 'M12 5v14', 'M12 9a3 3 0 1 0 0 6', 'M3 9h3v6H3M21 9h-3v6h3'],
    badge: ['M12 3 9.5 5.5 6 5l-.5 3.5L3 11l2.5 2.5L6 17l3.5-.5L12 19l2.5-2.5L18 17l.5-3.5L21 11l-2.5-2.5L18 5l-3.5.5L12 3Z', 'm9 11 2 2 4-4'],
    lineup: ['M4 6h2M10 6h10M4 12h2M10 12h10M4 18h2M10 18h10'],
    file: ['M6 3h8l4 4v14H6V3Z', 'M14 3v5h5M9 13h6M9 17h6'],
    chart: ['M4 20V10M10 20V4M16 20v-7M22 20H2'],
    gavel: ['m14 5 5 5M12 7l5 5M3 21l9-9M16.5 2.5l5 5-3 3-5-5 3-3ZM7.5 11.5l5 5-3 3-5-5 3-3Z'],
    wallet: ['M4 6h15v14H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h13', 'M15 11h7v5h-7a2.5 2.5 0 0 1 0-5Z'],
    bell: ['M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4'],
    download: ['M12 3v12M7 11l5 5 5-5', 'M4 21h16'],
    history: ['M4 12a8 8 0 1 0 2.3-5.7L4 8', 'M4 3v5h5M12 7v5l3 2'],
    switch: ['M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4'],
    lock: ['M6 10h12v11H6V10ZM9 10V7a3 3 0 0 1 6 0v3', 'M12 14v3'],
    logout: ['M10 5H5v14h5M14 8l4 4-4 4M18 12H9'],
    menu: ['M4 7h16M4 12h16M4 17h16'],
    check: ['m5 12 4 4L19 6'],
    clock: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', 'M12 7v5l3 2'],
    edit: ['M4 20h4L20 8l-4-4L4 16v4Z', 'm14 6 4 4'],
    refresh: ['M20 7v5h-5M4 17v-5h5', 'M18.5 12a7 7 0 0 0-12-5L4 9M5.5 12a7 7 0 0 0 12 5l2.5-2'],
    mail: ['M3 6h18v13H3V6Z', 'm3 7 9 7 9-7'],
    phone: ['M7 3h3l1.5 5-2 1.5a15 15 0 0 0 5 5L16 12l5 2v3c0 2-1.5 4-3.5 4C9.5 21 3 14.5 3 6.5 3 4.5 5 3 7 3Z'],
    image: ['M3 5h18v14H3V5Z', 'm3 11 4-4 6 6 3-3 5 5', 'M16 9h.01'],
    id: ['M3 5h18v14H3V5Z', 'M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM5 17c.5-2 5.5-2 6 0M14 9h4M14 13h4M14 17h3'],
};
</script>

<template>
    <svg :width="size" :height="size" :viewBox="variant === 'illustrated' ? '0 0 32 32' : '0 0 24 24'" fill="none" aria-hidden="true" :class="['proliga-icon', `proliga-icon--${variant}`]">
        <template v-if="variant === 'illustrated'">
            <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="9" :fill="iconPalette.soft" stroke="#ffffff" stroke-width="1.5" />
            <path d="M4 22.5C8.7 27.7 20.8 30 28 22V30H4Z" :fill="iconPalette.accent" opacity=".16" />
            <circle cx="25.2" cy="7.2" r="3.25" :fill="iconPalette.secondary" />
            <circle cx="25.2" cy="7.2" r="1.35" fill="#ffffff" opacity=".88" />
            <g transform="translate(4 4)" fill="none" stroke="#1B2A4A" :stroke-width="Math.max(strokeWidth, 1.75)" stroke-linecap="round" stroke-linejoin="round">
                <path v-for="path in (icons[name] ?? icons.file)" :key="path" :d="path" />
            </g>
        </template>
        <g v-else fill="none" stroke="currentColor" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round">
            <path v-for="path in (icons[name] ?? icons.file)" :key="path" :d="path" />
        </g>
    </svg>
</template>

<style scoped>
.proliga-icon { display: inline-block; flex: 0 0 auto; overflow: visible; }
.proliga-icon--illustrated { filter: drop-shadow(0 2px 2px rgba(27, 42, 74, .12)); }
</style>

<script setup lang="ts">
import { computed } from 'vue';
const props = withDefaults(defineProps<{ role: string; size?: number }>(), { size: 28 });
const kind = computed(() => {
  const r = props.role.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  if (r.includes('arbit')) return 'referee';
  if (r.includes('represent') || r.includes('dueno') || r.includes('propiet')) return 'representative';
  if (r.includes('jugador') || r.includes('player')) return 'player';
  if (r.includes('administr') || r.includes('president')) return 'admin';
  return 'member';
});
</script>
<template>
  <svg :width="size" :height="size" viewBox="0 0 48 48" role="img" :aria-label="`Icono de ${role}`" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4">
    <!-- Silbato de árbitro -->
    <template v-if="kind === 'referee'">
      <path d="M8 22c-4 4-4 12 1 16 5 5 14 3 17-3l4-10-10-7z"/><circle cx="16" cy="31" r="5"/>
      <path d="m18 17 12-9 11 7-15 11M30 8l4 8M6 35l-3 5 5 3 5-4"/>
    </template>
    <!-- Banderín del administrador de liga -->
    <template v-else-if="kind === 'admin'">
      <path d="M8 7h32L24 43zM14 17h20M18 29h12"/><circle cx="24" cy="16" r="5"/><path d="m24 12 2 2-1 3h-3l-1-3z" stroke-width="1.5"/>
    </template>
    <!-- Silueta de futbolista -->
    <template v-else-if="kind === 'player'">
      <circle cx="26" cy="8" r="4" fill="currentColor" stroke="none"/>
      <path d="m23 14-5 11 8 5 5 12M20 19l-10 5M27 19l9 5 8-3M26 30l-9 12" stroke-width="3.2"/>
      <circle cx="8" cy="38" r="5"/><path d="m6 35 3 2-1 3" stroke-width="1.3"/>
    </template>
    <!-- Portapapeles de representante -->
    <template v-else-if="kind === 'representative'">
      <path d="M14 8H9a3 3 0 0 0-3 3v31h29V11a3 3 0 0 0-3-3h-5"/><path d="M16 8a4 4 0 0 1 8 0h3v6H13V8z"/>
      <path d="m20 19-8 3v5c0 5 3 8 8 10 5-2 8-5 8-10v-5z"/><circle cx="20" cy="26" r="3"/><path d="M39 14v24m-3-20 3-4 3 4M36 39h6"/>
    </template>
    <template v-else><circle cx="24" cy="15" r="8"/><path d="M9 43c0-9 6-15 15-15s15 6 15 15"/></template>
  </svg>
</template>

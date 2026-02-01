<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
  <svg
      viewBox="0 0 100 100"
      xmlns="http://www.w3.org/2000/svg"
      class="handmade-shoe-icon"
      style="width: 100%; max-width: 300px; height: auto;"
  >
    <circle cx="50" cy="50" r="48" fill="none" stroke="#E5E7EB" stroke-width="1"/>

    <path
        d="M20 70 C 20 60, 30 55, 45 55 C 60 55, 85 60, 85 75 C 85 82, 75 85, 45 85 C 25 85, 20 80, 20 70 Z"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
    />
    <path d="M25 70 L 80 78" stroke="currentColor" stroke-width="1" stroke-dasharray="2 2" opacity="0.5"/>

    <path
        d="M65 45 C 60 45, 55 40, 55 35 C 55 30, 60 25, 70 25 C 80 25, 85 35, 85 40 L 80 55 L 70 50 Z"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linejoin="round"
    />

    <line x1="68" y1="52" x2="58" y2="65" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>

    <path
        d="M58 65 C 50 75, 40 65, 35 60"
        fill="none"
        stroke="currentColor"
        stroke-width="1"
        stroke-dasharray="3 2"
    />

    <path d="M22 78 L 35 82" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
</template>

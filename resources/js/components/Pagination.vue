<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
  links: Array<{
    url: string | null
    label: string
    active: boolean
  }>
}>()
</script>

<template>
  <div v-if="links.length > 3" class="flex flex-wrap gap-1">
    <template v-for="(link, key) in links" :key="key">
      <div
          v-if="link.url === null"
          class="px-4 py-2 text-sm leading-4 text-gray-400 border border-gray-300 rounded dark:border-gray-600 dark:text-gray-500"
          v-html="link.label"
      />
      <Link
          v-else
          class="px-4 py-2 text-sm leading-4 border border-gray-300 rounded hover:bg-gray-100 focus:border-blue-500 focus:text-blue-500 dark:border-gray-600 dark:hover:bg-gray-700 dark:focus:border-blue-400"
          :class="{ 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200': link.active }"
          :href="link.url"
          v-html="link.label"
      />
    </template>
  </div>
</template>
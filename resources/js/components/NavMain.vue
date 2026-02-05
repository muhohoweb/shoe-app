<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type NavItem } from '@/types';

defineProps<{
  items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
  <SidebarGroup class="px-2 py-4">
    <SidebarGroupLabel class="text-xs font-semibold uppercase tracking-wider text-stone-500 dark:text-stone-400">
      Platform
    </SidebarGroupLabel>
    <SidebarMenu class="mt-2 space-y-1">
      <SidebarMenuItem v-for="item in items" :key="item.title">
        <SidebarMenuButton
            as-child
            :is-active="isCurrentUrl(item.href)"
            :tooltip="item.title"
            class="rounded-lg transition-all duration-200
            text-stone-600 hover:bg-stone-200/70 hover:text-stone-900
            dark:text-stone-400 dark:hover:bg-stone-800/70 dark:hover:text-stone-100
            data-[active=true]:bg-stone-800 data-[active=true]:text-white data-[active=true]:shadow-sm
            dark:data-[active=true]:bg-stone-100 dark:data-[active=true]:text-stone-900"
        >
          <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
            <component :is="item.icon" class="h-5 w-5 shrink-0" />
            <span class="text-sm font-medium">{{ item.title }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
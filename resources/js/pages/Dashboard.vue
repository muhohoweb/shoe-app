<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import PieChart from "@/components/PieChart.vue";
import LineGraph from "@/components/LineGraph.vue";
import TransactionsGraph from "@/components/graphs/TransactionsGraph.vue";

defineProps<{
  transactions: Array<{
    id: number;
    status: 'pending' | 'completed' | 'failed';
  }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url,
  },
];
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
          <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Revenue</h3>
          <div class="h-48">
            <TransactionsGraph :data="transactions" />
          </div>
        </div>
        <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
          <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Distribution</h3>
          <div class="h-48">
            <PieChart />
          </div>
        </div>
        <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
          <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Trends</h3>
          <div class="h-48">
            <LineGraph />
          </div>
        </div>
      </div>
      <div class="flex-1 rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
        <PlaceholderPattern />
      </div>
    </div>
  </AppLayout>
</template>

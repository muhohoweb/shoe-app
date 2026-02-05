<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import TransactionsGraph from "@/components/graphs/TransactionsGraph.vue";
import OrdersGraph from "@/components/graphs/OrdersGraph.vue";

const props = defineProps<{
  transactions: Array<{ id: number; status: 'pending' | 'completed' | 'failed' }>;
  orders: Array<{ id: number; status: 'pending' | 'processing' | 'completed' | 'cancelled' }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
];

const stats = [
  { label: 'Total Orders', value: props.orders.length },
  { label: 'Completed', value: props.orders.filter(o => o.status === 'completed').length },
  { label: 'Pending', value: props.orders.filter(o => o.status === 'pending').length },
  { label: 'Transactions', value: props.transactions.length },
];
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">

      <!-- Stats Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="stat in stats"
            :key="stat.label"
            class="rounded-xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:border-sidebar-border dark:bg-gray-900"
        >
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
          <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stat.value }}</p>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">Transactions</h3>
          <div class="h-64">
            <TransactionsGraph :data="transactions" />
          </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:border-sidebar-border dark:bg-gray-900">
          <h3 class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">Orders by Status</h3>
          <div class="h-64">
            <OrdersGraph :data="orders" />
          </div>
        </div>
      </div>

      <!-- Placeholder Cards -->
      <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
          <p class="text-sm text-gray-400 dark:text-gray-500">Recent Activity</p>
        </div>
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
          <p class="text-sm text-gray-400 dark:text-gray-500">Top Customers</p>
        </div>
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
          <p class="text-sm text-gray-400 dark:text-gray-500">Revenue Summary</p>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
  transactions: Array<{
    id: number;
    phone_number: string;
    amount: string;
    account_reference: string;
    mpesa_receipt_number: string | null;
    status: 'pending' | 'completed' | 'failed';
    result_desc: string | null;
    created_at: string;
  }>;
}>();

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-KE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const statusStyles = {
  completed: 'bg-emerald-50 dark:bg-emerald-900/20',
  failed: 'bg-red-50 dark:bg-red-900/20',
  pending: 'bg-amber-50 dark:bg-amber-900/20',
};

const statusBadge = {
  completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
  failed: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Transactions', href: '#' },
];
</script>

<template>
  <Head title="Transactions" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6">
      <h1 class="text-2xl font-semibold text-stone-900 dark:text-stone-100 mb-6">M-Pesa Transactions</h1>

      <div class="overflow-hidden rounded-xl border border-stone-200 dark:border-stone-700">
        <table class="w-full">
          <thead>
          <tr class="bg-stone-100 dark:bg-stone-800">
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Phone</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Reference</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Receipt</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Result</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-600 dark:text-stone-300">Date</th>
          </tr>
          </thead>
          <tbody class="divide-y divide-stone-200 dark:divide-stone-700">
          <tr
              v-for="(tx,index) in transactions"
              :key="tx.id"
              :class="statusStyles[tx.status]"
          >
            <td class="px-4 py-3 text-sm text-stone-900 dark:text-stone-100">{{ index++ }}</td>
            <td class="px-4 py-3 text-sm text-stone-900 dark:text-stone-100">{{ tx.phone_number }}</td>
            <td class="px-4 py-3 text-sm font-medium text-stone-900 dark:text-stone-100">{{ tx.amount }}</td>
            <td class="px-4 py-3 text-sm text-stone-600 dark:text-stone-400">{{ tx.account_reference }}</td>
            <td class="px-4 py-3 text-sm text-stone-600 dark:text-stone-400">{{ tx.mpesa_receipt_number ?? '-' }}</td>
            <td class="px-4 py-3">
                <span :class="[statusBadge[tx.status], 'inline-flex rounded-full px-2 py-1 text-xs font-medium']">
                  {{ tx.status }}
                </span>
            </td>
            <td class="px-4 py-3 text-sm text-stone-600 dark:text-stone-400">{{ tx.result_desc ?? '-' }}</td>
            <td class="px-4 py-3 text-sm text-stone-500 dark:text-stone-400">{{ formatDate(tx.created_at) }}</td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
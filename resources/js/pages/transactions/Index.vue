<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import transactions from '@/routes/transactions';
import { type BreadcrumbItem } from '@/types';
import { ref, watch } from 'vue';

//comment
const props = defineProps<{
  transactions: {
    data: Array<{
      id: number;
      phone_number: string;
      amount: string;
      account_reference: string;
      mpesa_receipt_number: string | null;
      status: 'pending' | 'completed' | 'failed';
      created_at: string;
    }>;
  };
  filters: {
    status: string | null;
  };
}>();

const statusFilter = ref(props.filters.status ?? '');

watch(statusFilter, (value) => {
  router.get(transactions.index(), { status: value || undefined }, {
    preserveState: true,
    replace: true,
  });
});

const rowClass = (status: string) => {
  return {
    'bg-green-50': status === 'completed',
    'bg-red-50': status === 'failed',
    'bg-orange-50': status === 'pending',
  };
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url,
  },
  {
    title: 'Transactions',
    href: '#',
  },
];
</script>

<template>
  <Head title="Transactions" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-semibold">M-Pesa Transactions</h1>

        <select v-model="statusFilter" class="border rounded px-3 py-2">
          <option value="">All Statuses</option>
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
          <option value="failed">Failed</option>
        </select>
      </div>

      <table class="w-full border-collapse border">
        <thead>
        <tr class="bg-gray-100">
          <th class="border p-2 text-left">Phone</th>
          <th class="border p-2 text-left">Amount</th>
          <th class="border p-2 text-left">Reference</th>
          <th class="border p-2 text-left">Receipt</th>
          <th class="border p-2 text-left">Status</th>
          <th class="border p-2 text-left">Date</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="tx in transactions.data" :key="tx.id" :class="rowClass(tx.status)">
          <td class="border p-2">{{ tx.phone_number }}</td>
          <td class="border p-2">{{ tx.amount }}</td>
          <td class="border p-2">{{ tx.account_reference }}</td>
          <td class="border p-2">{{ tx.mpesa_receipt_number ?? '-' }}</td>
          <td class="border p-2">{{ tx.status }}</td>
          <td class="border p-2">{{ tx.created_at }}</td>
        </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
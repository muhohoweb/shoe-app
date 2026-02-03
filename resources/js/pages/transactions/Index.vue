<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

defineProps<{
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
}>();

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
      <h1 class="text-2xl font-semibold mb-4">M-Pesa Transactions</h1>

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
        <tr v-for="tx in transactions.data" :key="tx.id">
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
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { RefreshCw, Wallet, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
  balance?: string | null;
  balanceError?: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'M-Pesa Settings',
    href: '/settings/mpesa',
  },
];

const balanceForm = useForm({});
const balanceData = ref<any>(null);
const isPolling = ref(false);
const pollingInterval = ref<number | null>(null);

const queryBalance = () => {
  balanceForm.post('/settings/mpesa/balance', {
    preserveScroll: true,
    onSuccess: () => {
      // Start polling for balance result
      startPolling();
    },
  });
};

const startPolling = () => {
  isPolling.value = true;
  let attempts = 0;
  const maxAttempts = 20; // 1 minute (20 * 3 seconds)

  pollingInterval.value = window.setInterval(async () => {
    attempts++;

    try {
      const res = await fetch('/api/mpesa/balance');
      const data = await res.json();

      if (data.success && data.balance) {
        balanceData.value = data.balance;
        stopPolling();
      }
    } catch (e) {
      console.error('Poll error', e);
    }

    if (attempts >= maxAttempts) {
      stopPolling();
    }
  }, 3000);
};

const stopPolling = () => {
  isPolling.value = false;
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
};

// Check for existing balance on mount
onMounted(async () => {
  try {
    const res = await fetch('/api/mpesa/balance');
    const data = await res.json();
    if (data.success && data.balance) {
      balanceData.value = data.balance;
    }
  } catch (e) {
    // No cached balance
  }
});

onUnmounted(() => {
  stopPolling();
});

const parseBalanceString = (balanceStr: string | null) => {
  if (!balanceStr) return [];

  // Parse format: "Working Account|KES|1000.00|1000.00|0.00|0.00&..."
  const accounts = balanceStr.split('&');
  return accounts.map(account => {
    const parts = account.split('|');
    return {
      name: parts[0] || 'Unknown',
      currency: parts[1] || 'KES',
      available: parts[2] || '0.00',
      current: parts[3] || '0.00',
    };
  });
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="M-Pesa Settings" />

    <SettingsLayout>
      <div class="space-y-6">
        <Heading
            title="M-Pesa Integration"
            description="View your M-Pesa account balance and transaction settings"
        />

        <!-- Balance Card -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Wallet class="h-5 w-5" />
              Account Balance
            </CardTitle>
            <CardDescription>
              Query your M-Pesa Paybill account balance
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <!-- Success Message -->
            <Alert v-if="balance" class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
              <RefreshCw class="h-4 w-4 text-blue-600" />
              <AlertDescription class="text-blue-700 dark:text-blue-300">
                {{ balance }}
              </AlertDescription>
            </Alert>

            <!-- Error Message -->
            <Alert v-if="balanceError" variant="destructive">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>{{ balanceError }}</AlertDescription>
            </Alert>

            <!-- Balance Display -->
            <div v-if="balanceData" class="space-y-3">
              <Alert class="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <CheckCircle2 class="h-4 w-4 text-green-600" />
                <AlertDescription class="text-green-700 dark:text-green-300">
                  Balance retrieved successfully
                </AlertDescription>
              </Alert>

              <!-- Account Balance Info -->
              <div v-if="balanceData.AccountBalance" class="rounded-lg border p-4 space-y-2">
                <p class="text-sm text-muted-foreground">Account Balance</p>
                <div
                    v-for="(account, index) in parseBalanceString(balanceData.AccountBalance)"
                    :key="index"
                    class="flex justify-between items-center py-2 border-b last:border-0"
                >
                  <span class="font-medium">{{ account.name }}</span>
                  <span class="text-lg font-bold">
                    {{ account.currency }} {{ Number(account.available).toLocaleString() }}
                  </span>
                </div>
              </div>

              <!-- Raw Data (for debugging) -->
              <details class="text-xs text-muted-foreground">
                <summary class="cursor-pointer">View raw data</summary>
                <pre class="mt-2 p-2 bg-muted rounded text-xs overflow-auto">{{ JSON.stringify(balanceData, null, 2) }}</pre>
              </details>
            </div>

            <!-- Polling Indicator -->
            <div v-if="isPolling" class="flex items-center gap-2 text-sm text-muted-foreground">
              <RefreshCw class="h-4 w-4 animate-spin" />
              Waiting for balance response...
            </div>

            <!-- Query Button -->
            <Button
                @click="queryBalance"
                :disabled="balanceForm.processing || isPolling"
            >
              <RefreshCw
                  class="h-4 w-4 mr-2"
                  :class="{ 'animate-spin': balanceForm.processing || isPolling }"
              />
              {{ balanceForm.processing ? 'Querying...' : 'Query Balance' }}
            </Button>
          </CardContent>
        </Card>

        <!-- Transaction Stats Card (Optional) -->
        <Card>
          <CardHeader>
            <CardTitle>Transaction Statistics</CardTitle>
            <CardDescription>
              Overview of M-Pesa transactions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">
              Coming soon: View transaction summaries and reports.
            </p>
          </CardContent>
        </Card>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index, store, update, destroy, toggle } from '@/routes/jobs';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import {
  Bell,
  Mail,
  Settings,
  Trash2,
  Edit,
  Plus,
  Clock,
  CheckCircle2,
  XCircle,
  Calendar,
} from 'lucide-vue-next';

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { toast } from 'vue-sonner';

// Props from Inertia
const props = defineProps<{
  success: boolean;
  data: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Report', href: index.url() },
];

// State
const isEnabled = ref(true);
const isSubmitting = ref(false);
const isEditMode = ref(false);
const editingScheduleId = ref<number | null>(null);
const schedules = ref(props.data || []);
const isLoadingSchedules = ref(false);

const reportForm = ref({
  email: '',
  frequency: 'daily',
  scheduled_time: '08:00',
});

// Watch for prop changes (when Inertia reloads)
watch(() => props.data, (newData) => {
  schedules.value = newData || [];
  isLoadingSchedules.value = false;
});

// Refresh schedules via Inertia
const fetchSchedules = () => {
  isLoadingSchedules.value = true;
  router.reload({ only: ['data'] });
};

// Load schedule for editing
const editSchedule = (schedule: any) => {
  isEditMode.value = true;
  editingScheduleId.value = schedule.id;
  reportForm.value.email = schedule.email;
  reportForm.value.frequency = schedule.frequency;
  reportForm.value.scheduled_time = schedule.scheduled_time;
  isEnabled.value = schedule.is_enabled;

  toast.info('Edit Mode', { description: `Editing schedule for ${schedule.email}` });
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Reset form
const resetForm = () => {
  isEditMode.value = false;
  editingScheduleId.value = null;
  reportForm.value.email = '';
  reportForm.value.frequency = 'daily';
  reportForm.value.scheduled_time = '08:00';
  isEnabled.value = true;
};

// Submit form (create or update)
const handleReportScheduleSubmit = async () => {
  if (!reportForm.value.email) {
    toast.error('Validation Error', { description: 'Please enter an email address.' });
    return;
  }

  isSubmitting.value = true;

  try {
    const payload = {
      email: reportForm.value.email,
      frequency: reportForm.value.frequency,
      scheduled_time: reportForm.value.scheduled_time,
      is_enabled: isEnabled.value,
    };

    const response = isEditMode.value && editingScheduleId.value
        ? await axios.put(update.url(editingScheduleId.value), payload)
        : await axios.post(store.url(), payload);

    if (response.data.success) {
      toast.success(response.data.message);
      resetForm();
      fetchSchedules();
    }
  } catch (error: any) {
    if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat();
      toast.error('Validation Error', { description: (errors as string[]).join(', ') });
    } else {
      toast.error('Error', { description: error.response?.data?.message || 'Failed to save schedule.' });
    }
  } finally {
    isSubmitting.value = false;
  }
};

// Delete schedule
const deleteSchedule = async (id: number, email: string) => {
  toast.promise(
      axios.delete(destroy.url(id)),
      {
        loading: 'Deleting schedule...',
        success: () => {
          fetchSchedules();
          if (editingScheduleId.value === id) resetForm();
          return `Schedule for ${email} has been deleted`;
        },
        error: 'Failed to delete schedule',
      }
  );
};

// Toggle schedule status
const toggleScheduleStatus = async (schedule: any) => {
  try {
    const response = await axios.post(toggle.url(schedule.id));
    if (response.data.success) {
      toast.success(response.data.message);
      fetchSchedules();
    }
  } catch (error) {
    toast.error('Error', { description: 'Failed to toggle schedule status.' });
  }
};

// Get frequency label
const getFrequencyLabel = (frequency: string) => {
  const labels: Record<string, string> = {
    'daily': 'Daily',
    'weekly': 'Weekly',
    'bi-weekly': 'Bi-weekly',
    'monthly': 'Monthly',
    'quarterly': 'Quarterly',
  };
  return labels[frequency] || frequency;
};

// Format date
const formatDate = (date: string) => {
  if (!date) return 'Never';
  return new Date(date).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<template>
  <Head title="Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">

      <!-- Header Section -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Report</h1>
          <p class="text-muted-foreground">Welcome back! Here's your clinic overview.</p>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid gap-6 lg:grid-cols-3">


        <!-- Create/Edit Schedule Form Card -->
        <Card class="lg:col-span-1">
          <CardHeader>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Bell class="h-5 w-5" />
                <CardTitle>{{ isEditMode ? 'Edit Schedule' : 'New Schedule' }}</CardTitle>
              </div>
              <Button
                  v-if="isEditMode"
                  variant="ghost"
                  size="sm"
                  @click="resetForm"
              >
                Cancel
              </Button>
            </div>
            <CardDescription>
              {{ isEditMode ? 'Update the scheduled report settings' : 'Schedule automated reports to be sent to your email' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <form @submit.prevent="handleReportScheduleSubmit" class="space-y-4">
              <!-- Enable/Disable Toggle -->
              <div class="flex items-center justify-between space-x-2 rounded-lg border p-4">
                <div class="flex-1 space-y-0.5">
                  <Label class="text-base font-medium">Enable Schedule</Label>
                  <p class="text-sm text-muted-foreground">
                    Receive scheduled reports automatically
                  </p>
                </div>
                <Switch
                    v-model:checked="isEnabled"
                    id="report-enabled"
                />
              </div>

              <!-- Email Input -->
              <div class="space-y-2">
                <Label for="report-email">Email Address</Label>
                <div class="relative">
                  <Mail class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                  <Input
                      id="report-email"
                      v-model="reportForm.email"
                      type="email"
                      placeholder="your.email@clinic.com"
                      class="pl-9"
                      :disabled="!isEnabled"
                      required
                  />
                </div>
              </div>

              <!-- Frequency Selection -->
              <div class="space-y-2">
                <Label for="report-frequency">Report Frequency</Label>
                <Select
                    v-model="reportForm.frequency"
                    :disabled="!isEnabled"
                >
                  <SelectTrigger id="report-frequency">
                    <SelectValue placeholder="Select frequency" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="daily">Daily (Every day at 8:00 AM)</SelectItem>
                    <SelectItem value="weekly">Weekly (Every Monday)</SelectItem>
                    <SelectItem value="bi-weekly">Bi-weekly (1st & 15th of month)</SelectItem>
                    <SelectItem value="monthly">Monthly (1st of every month)</SelectItem>
                    <SelectItem value="quarterly">Quarterly (Every 3 months)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <!-- Scheduled Time -->
              <div class="space-y-2">
                <Label for="scheduled-time">Scheduled Time</Label>
                <div class="relative">
                  <Clock class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                  <Input
                      id="scheduled-time"
                      v-model="reportForm.scheduled_time"
                      type="time"
                      class="pl-9"
                      :disabled="!isEnabled"
                  />
                </div>
              </div>

              <Separator />

              <!-- Submit Button -->
              <Button
                  type="submit"
                  class="w-full"
                  :disabled="isSubmitting || !isEnabled"
              >
                <Plus v-if="!isEditMode" class="mr-2 h-4 w-4" />
                <Settings v-else class="mr-2 h-4 w-4" />
                {{ isSubmitting ? 'Saving...' : (isEditMode ? 'Update Schedule' : 'Create Schedule') }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <!-- Existing Schedules Card -->
        <Card class="lg:col-span-2">
          <CardHeader>
            <div class="flex items-center justify-between">
              <div>
                <CardTitle>Scheduled Reports</CardTitle>
                <CardDescription>
                  Manage your automated report schedules
                </CardDescription>
              </div>
              <Button
                  variant="outline"
                  size="sm"
                  @click="fetchSchedules"
                  :disabled="isLoadingSchedules"
              >
                {{ isLoadingSchedules ? 'Loading...' : 'Refresh' }}
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <!-- Loading State -->
            <div v-if="isLoadingSchedules" class="flex items-center justify-center py-8">
              <div class="text-muted-foreground">Loading schedules...</div>
            </div>

            <!-- Empty State -->
            <div v-else-if="schedules.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
              <Calendar class="h-12 w-12 text-muted-foreground mb-4" />
              <h3 class="text-lg font-semibold mb-2">No Schedules Yet</h3>
              <p class="text-sm text-muted-foreground mb-4">
                Create your first automated report schedule to get started.
              </p>
            </div>

            <!-- Schedules List -->
            <div v-else class="space-y-4">
              <div
                  v-for="schedule in schedules"
                  :key="schedule.id"
                  class="border rounded-lg p-4 hover:bg-accent/50 transition-colors"
                  :class="{ 'border-primary': editingScheduleId === schedule.id }"
              >
                <div class="flex items-start justify-between">
                  <!-- Schedule Info -->
                  <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-2">
                      <Mail class="h-4 w-4 text-muted-foreground" />
                      <span class="font-medium">{{ schedule.email }}</span>
                      <Badge :variant="schedule.is_enabled ? 'default' : 'secondary'">
                        <CheckCircle2 v-if="schedule.is_enabled" class="h-3 w-3 mr-1" />
                        <XCircle v-else class="h-3 w-3 mr-1" />
                        {{ schedule.is_enabled ? 'Active' : 'Inactive' }}
                      </Badge>
                    </div>

                    <div class="flex items-center gap-4 text-sm text-muted-foreground">
                      <div class="flex items-center gap-1">
                        <Calendar class="h-3 w-3" />
                        {{ getFrequencyLabel(schedule.frequency) }}
                      </div>
                      <div class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        {{ schedule.scheduled_time }}
                      </div>
                    </div>

                    <div v-if="schedule.last_run_at" class="text-xs text-muted-foreground">
                      Last run: {{ formatDate(schedule.last_run_at) }}
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleScheduleStatus(schedule)"
                        :title="schedule.is_enabled ? 'Disable' : 'Enable'"
                    >
                      <CheckCircle2 v-if="schedule.is_enabled" class="h-4 w-4" />
                      <XCircle v-else class="h-4 w-4" />
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon"
                        @click="editSchedule(schedule)"
                        title="Edit"
                    >
                      <Edit class="h-4 w-4" />
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon"
                        @click="deleteSchedule(schedule.id, schedule.email)"
                        title="Delete"
                        class="text-destructive hover:text-destructive"
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>
    </div>
  </AppLayout>
</template>
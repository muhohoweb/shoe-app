<script setup lang="ts">
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps<{
  data: Array<{ id: number; status: 'pending' | 'processing' | 'completed' | 'cancelled' }>;
}>();

const chartData = computed(() => {
  const counts = { pending: 0, processing: 0, completed: 0, cancelled: 0 };
  props.data.forEach(o => counts[o.status]++);

  return {
    labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
    datasets: [{
      label: 'Orders',
      data: [counts.pending, counts.processing, counts.completed, counts.cancelled],
      backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
    }]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
};
</script>

<template>
  <Bar :data="chartData" :options="chartOptions" />
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps<{
  data: Array<{
    status: 'pending' | 'completed' | 'failed';
  }>;
}>();

const chartData = computed(() => {
  const completed = props.data.filter(t => t.status === 'completed').length;
  const pending = props.data.filter(t => t.status === 'pending').length;
  const failed = props.data.filter(t => t.status === 'failed').length;

  return {
    labels: ['Completed', 'Pending', 'Failed'],
    datasets: [{
      data: [completed, pending, failed],
      backgroundColor: ['#22c55e', '#f97316', '#ef4444'],
    }]
  };
});

const chartOptions = {
  responsive: true,
  plugins: {
    legend: {
      position: 'bottom' as const,
    },
    title: {
      display: true,
      text: 'Transaction Status',
    },
  },
};
</script>

<template>
  <div class="w-80">
    <Doughnut :data="chartData" :options="chartOptions" />
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps<{
  data: Array<{
    status: 'pending' | 'completed' | 'failed';
    amount: string;
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
      hoverOffset: 8,
    }]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: {
        boxWidth: 12,
        padding: 8,
        font: { size: 11 },
      },
    },
    title: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context: any) => {
          const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0);
          const percentage = ((context.raw / total) * 100).toFixed(1);
          return `${context.label}: ${context.raw} (${percentage}%)`;
        }
      }
    }
  },
};
</script>

<template>
  <Doughnut :data="chartData" :options="chartOptions" />
</template>
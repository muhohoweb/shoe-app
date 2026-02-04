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
      label: 'Transactions',
      data: [completed, pending, failed],
      backgroundColor: ['#22c55e', '#f97316', '#ef4444'],
      hoverBackgroundColor: ['#16a34a', '#ea580c', '#dc2626'],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 12,
    }]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  animation: {
    animateRotate: true,
    animateScale: true,
    duration: 800,
    easing: 'easeOutQuart' as const,
  },
  interaction: {
    mode: 'nearest' as const,
  },
  plugins: {
    legend: {
      position: 'top' as const,
      labels: {
        padding: 16,
        usePointStyle: true,
        pointStyle: 'circle',
        font: { size: 12 },
      },
    },
    title: {
      display: true,
      text: 'Transaction Status',
      font: { size: 14, weight: 'bold' as const },
      padding: { bottom: 16 },
    },
    tooltip: {
      enabled: true,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleFont: { size: 13 },
      bodyFont: { size: 12 },
      padding: 12,
      cornerRadius: 8,
      callbacks: {
        label: (context: any) => {
          const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0);
          const percentage = ((context.raw / total) * 100).toFixed(1);
          return ` ${context.label}: ${context.raw} (${percentage}%)`;
        }
      }
    }
  },
};
</script>

<template>
  <Doughnut :data="chartData" :options="chartOptions" />
</template>
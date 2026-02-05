<template>
  <Line :options="chartOptions" :data="chartData" />
</template>

<script setup lang="ts">
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler)

const chartData = {
  labels: ['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04'],
  datasets: [{
    label: 'Values',
    data: [40, 20, 12, 35],
    borderColor: '#3b82f6',
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    fill: true,
    tension: 0.4,
    pointBackgroundColor: '#3b82f6',
    pointRadius: 5,
    pointHoverRadius: 8
  }]
}

const chartOptions = {
  responsive: true,
  plugins: {
    legend: {
      labels: { font: { size: 14 } }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(0,0,0,0.05)' }
    },
    x: {
      grid: { display: false }
    }
  }
}
</script>
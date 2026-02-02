<script setup lang="ts">
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale)

export default {
  name: 'LineChart',
  components: { Line },
  data() {
    return {
      chartData: {
        labels: ['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04'],
        datasets: [{
          label: 'Values',
          data: [40, 20, 12, 35],
          borderColor: '#3b82f6',
          tension: 0.1
        }]
      },
      chartOptions: {
        responsive: true
      }
    }
  }
}
</script>

<template>

</template>

<style scoped>

</style>
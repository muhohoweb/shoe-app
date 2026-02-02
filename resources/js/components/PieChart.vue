<template>
  <Pie id="my-chart-id" :options="chartOptions" :data="chartData" />
</template>

<script>
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, ArcElement)

export default {
  name: 'PieChart',
  components: { Pie },
  data() {
    return {
      chartData: {
        labels: ['January', 'February', 'March'],
        datasets: [{
          backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 10,
          data: [40, 20, 12]
        }]
      },
      chartOptions: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              font: { size: 14 },
              usePointStyle: true
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            padding: 12,
            titleFont: { size: 14 },
            bodyFont: { size: 13 }
          }
        }
      }
    }
  }
}
</script>
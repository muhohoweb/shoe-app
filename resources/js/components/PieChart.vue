<template>
  <Pie
      id="my-chart-id"
      :options="chartOptions"
      :data="chartData"
  />
</template>

<script>
import {Pie} from 'vue-chartjs'
import {Chart as ChartJS, Title, Tooltip, Legend, ArcElement} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, ArcElement)

export default {
  name: 'PieChart',
  components: {Pie},
  data() {
    return {
      chartData: {
        labels: ['January', 'February', 'March'],
        datasets: [{
          backgroundColor: ['#41B883', '#E46651', '#00D8FF', '#DD1B16'],
          data: [40, 20, 12]
          }]
      },
      chartOptions: {
        responsive: true
      }
    }
  }
}
</script>
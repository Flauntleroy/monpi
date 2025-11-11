<template>
  <div class="network-diagnostic">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b mb-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-900">🌐 Network Diagnostic Dashboard</h1>
          <div class="text-sm text-gray-500">Last updated: {{ formatDate(timestamp) }}</div>
        </div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Summary Stats -->
      <div class="grid grid-cols-4 gap-6 mb-6">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Total Endpoints</dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900">
                      {{ currentStatus.bpjs.length + currentStatus.baseline.length }}
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900">
                      {{ getSuccessRate() }}%
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Avg Response Time</dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900">
                      {{ getAverageResponseTime() }}ms
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Active Issues</dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900">
                      {{ getActiveIssuesCount() }}
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BPJS Endpoints Panel -->
      <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
          <h2 class="text-lg font-medium text-gray-900">
            <span class="mr-2">🏥</span>BPJS Endpoints
          </h2>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <div v-for="endpoint in currentStatus.bpjs" :key="endpoint.name" 
                 class="bg-gray-50 rounded-lg p-4 transition-all duration-200 hover:shadow-md">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-medium text-gray-900">{{ endpoint.name }}</h3>
                  <p class="text-sm text-gray-500">{{ getEndpointDescription(endpoint.name) }}</p>
                </div>
                <div class="flex items-center space-x-4">
                  <div class="text-right">
                    <div class="text-sm font-medium text-gray-900">Response Time</div>
                    <div :class="getResponseTimeColor(endpoint.response_time)">
                      {{ endpoint.response_time }}ms
                    </div>
                  </div>
                  <div class="flex flex-col items-end">
                    <div class="text-sm font-medium text-gray-900">Status</div>
                    <div class="flex items-center">
                      <span :class="getStatusBadgeClass(endpoint.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                        {{ endpoint.code }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Baseline Endpoints Panel -->
      <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
          <h2 class="text-lg font-medium text-gray-900">
            <span class="mr-2">🌐</span>Baseline Network Endpoints
          </h2>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-2 gap-4">
            <div v-for="endpoint in currentStatus.baseline" :key="endpoint.name" 
                 class="bg-gray-50 rounded-lg p-4 transition-all duration-200 hover:shadow-md">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-medium text-gray-900">{{ endpoint.name }}</h3>
                  <p class="text-sm text-gray-500">{{ getEndpointDescription(endpoint.name) }}</p>
                </div>
                <div class="flex items-center space-x-4">
                  <div class="text-right">
                    <div class="text-sm font-medium text-gray-900">Response Time</div>
                    <div :class="getResponseTimeColor(endpoint.response_time)">
                      {{ endpoint.response_time }}ms
                    </div>
                  </div>
                  <div class="flex flex-col items-end">
                    <div class="text-sm font-medium text-gray-900">Status</div>
                    <div class="flex items-center">
                      <span :class="getStatusBadgeClass(endpoint.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                        {{ endpoint.code }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Performance Analysis Panel -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <!-- Latency Analysis -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h2 class="text-lg font-medium text-gray-900">
              <span class="mr-2">⚡</span>Latency Analysis
            </h2>
          </div>
          <div class="p-6">
            <div class="space-y-6">
              <div class="bg-gray-50 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <div class="text-sm font-medium text-gray-500">BPJS Average</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">
                      {{ latencyComparison.bpjs_avg }}ms
                    </div>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-500">Baseline Average</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">
                      {{ latencyComparison.baseline_avg }}ms
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-sm font-medium text-gray-500">Performance Ratio</div>
                  <div class="mt-1">
                    <div class="flex items-center">
                      <span class="text-2xl font-semibold" :class="getPerformanceColor(latencyComparison.ratio)">
                        {{ latencyComparison.ratio }}x
                      </span>
                      <span class="ml-2 text-sm text-gray-500">
                        ({{ latencyComparison.difference }}ms difference)
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Uptime Analysis -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h2 class="text-lg font-medium text-gray-900">
              <span class="mr-2">📈</span>Uptime Analysis
            </h2>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="type in ['bpjs', 'baseline']" :key="type">
                <h3 class="text-sm font-medium text-gray-500 mb-3 capitalize">{{ type }} Services</h3>
                <div class="space-y-3">
                  <div v-for="stat in uptimeStats[type]" :key="stat.name" 
                       class="bg-gray-50 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm font-medium text-gray-700">{{ stat.name }}</span>
                      <span class="text-sm font-semibold" :class="getUptimeColor(stat.uptime)">
                        {{ stat.uptime }}%
                      </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div class="h-2 rounded-full transition-all duration-300" 
                           :class="getUptimeBarColor(stat.uptime)"
                           :style="{ width: stat.uptime + '%' }">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Diagnostic Panel -->
      <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
          <h2 class="text-lg font-medium text-gray-900">
            <span class="mr-2">🔍</span>Network Diagnosis
          </h2>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <h3 class="text-sm font-medium text-gray-500 mb-4">Current Diagnosis</h3>
              <div class="space-y-3">
                <div v-for="(item, index) in diagnosis" :key="index" 
                     class="flex items-start space-x-3 bg-gray-50 rounded-lg p-3">
                  <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-700">{{ item }}</p>
                </div>
              </div>
            </div>
            <div>
              <h3 class="text-sm font-medium text-gray-500 mb-4">Recommendations</h3>
              <div class="space-y-3">
                <div v-for="(item, index) in recommendations" :key="index" 
                     class="flex items-start space-x-3 bg-gray-50 rounded-lg p-3">
                  <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-700">{{ item }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const currentStatus = ref({ bpjs: [], baseline: [] })
const latencyComparison = ref({})
const uptimeStats = ref({ bpjs: [], baseline: [] })
const diagnosis = ref([])
const recommendations = ref([])
const timestamp = ref('')

const fetchDiagnosticData = async () => {
  try {
    const response = await axios.get('/bpjs-monitoring/network-diagnostic-data')
    const data = response.data
    
    currentStatus.value = data.current_status
    latencyComparison.value = data.latency_comparison
    uptimeStats.value = data.uptime_stats
    diagnosis.value = data.diagnosis
    recommendations.value = data.recommendations
    timestamp.value = data.timestamp
  } catch (error) {
    console.error('Error fetching diagnostic data:', error)
  }
}

const getEndpointDescription = (name) => {
  const descriptions = {
    'BPJS Diagnosa': 'Referensi data diagnosa BPJS',
    'BPJS Poli': 'Referensi data poli BPJS',
    'BPJS Faskes': 'Referensi data fasilitas kesehatan BPJS',
    'Google DNS': 'Google Public DNS Service',
    'Cloudflare DNS': 'Cloudflare DNS over HTTPS',
    'JSONPlaceholder': 'Test JSON API endpoint',
    'HTTPBin Status': 'HTTP status test endpoint',
    'GitHub API': 'GitHub API health check'
  }
  return descriptions[name] || name
}

const getStatusBadgeClass = (status) => {
  return {
    'success': 'bg-green-100 text-green-800',
    'error': 'bg-red-100 text-red-800',
    'warning': 'bg-yellow-100 text-yellow-800'
  }[status] || 'bg-gray-100 text-gray-800'
}

const getResponseTimeColor = (time) => {
  if (!time && time !== 0) return 'text-gray-500'
  const rt = Math.round(time)
  if (rt < 200) return 'text-green-600'
  if (rt <= 1000) return 'text-yellow-600'
  return 'text-red-600'
}

const getPerformanceColor = (ratio) => {
  if (ratio <= 1.5) return 'text-green-600'
  if (ratio <= 2) return 'text-yellow-600'
  return 'text-red-600'
}

const getUptimeColor = (uptime) => {
  if (uptime >= 99) return 'text-green-600'
  if (uptime >= 95) return 'text-yellow-600'
  return 'text-red-600'
}

const getUptimeBarColor = (uptime) => {
  if (uptime >= 99) return 'bg-green-500'
  if (uptime >= 95) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getSuccessRate = () => {
  const allEndpoints = [...currentStatus.value.bpjs, ...currentStatus.value.baseline]
  if (allEndpoints.length === 0) return 0
  
  const successful = allEndpoints.filter(e => e.status === 'success').length
  return Math.round((successful / allEndpoints.length) * 100)
}

const getAverageResponseTime = () => {
  const allEndpoints = [...currentStatus.value.bpjs, ...currentStatus.value.baseline]
  if (allEndpoints.length === 0) return 0
  
  const times = allEndpoints.map(e => e.response_time).filter(t => t)
  if (times.length === 0) return 0
  
  return Math.round(times.reduce((a, b) => a + b, 0) / times.length)
}

const getActiveIssuesCount = () => {
  const allEndpoints = [...currentStatus.value.bpjs, ...currentStatus.value.baseline]
  return allEndpoints.filter(e => e.status === 'error').length
}

const formatDate = (isoString) => {
  if (!isoString) return ''
  return new Date(isoString).toLocaleString()
}

// Initial fetch
onMounted(() => {
  fetchDiagnosticData()
  // Auto refresh every 30 seconds
  setInterval(fetchDiagnosticData, 30000)
})
</script>

<style scoped>
.network-diagnostic {
  @apply max-w-7xl mx-auto;
}
</style>

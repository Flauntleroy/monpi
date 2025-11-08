<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import axios from 'axios'

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Network Diagnostic',
        href: '/dashboard',
    },
];

const currentStatus = ref({ bpjs: [], baseline: [] })
const latencyComparison = ref({})
const uptimeStats = ref({ bpjs: [], baseline: [] })
const diagnosis = ref([])
const recommendations = ref([])
const timestamp = ref('')
const customEndpoints = ref([])
const showAddEndpointModal = ref(false)
const newEndpoint = ref({
  name: '',
  url: '',
  method: 'GET',
  headers: {},
  body: ''
})

const fetchDiagnosticData = async () => {
  try {
    const response = await axios.get('/bpjs-monitoring/data')
    const data = response.data
    
    // Transform data structure to match our needs
    const bpjsEndpoints = data.endpoints?.filter(e => e.name.includes('BPJS')) || []
    const baselineEndpoints = data.endpoints?.filter(e => !e.name.includes('BPJS')) || []
    
    currentStatus.value = { 
      bpjs: bpjsEndpoints, 
      baseline: baselineEndpoints 
    }
    
    // Calculate simple metrics
    const allEndpoints = data.endpoints || []
    const bpjsAvg = bpjsEndpoints.reduce((sum, e) => sum + (e.response_time || 0), 0) / bpjsEndpoints.length || 0
    const baselineAvg = baselineEndpoints.reduce((sum, e) => sum + (e.response_time || 0), 0) / baselineEndpoints.length || 0
    
    latencyComparison.value = {
      bpjs_avg: Math.round(bpjsAvg),
      baseline_avg: Math.round(baselineAvg),
      difference: Math.round(bpjsAvg - baselineAvg),
      ratio: baselineAvg > 0 ? Math.round((bpjsAvg / baselineAvg) * 100) / 100 : 0
    }
    
    // Simple uptime calculation (mock for now)
    uptimeStats.value = {
      bpjs: bpjsEndpoints.map(e => ({
        name: e.name,
        uptime: e.status === 'success' ? 100 : 85,
        total_checks: 100,
        successful: e.status === 'success' ? 100 : 85
      })),
      baseline: baselineEndpoints.map(e => ({
        name: e.name,
        uptime: e.status === 'success' ? 100 : 90,
        total_checks: 100,
        successful: e.status === 'success' ? 100 : 90
      }))
    }
    
    // Simple diagnosis
    const bpjsErrors = bpjsEndpoints.filter(e => e.status !== 'success').length
    const baselineErrors = baselineEndpoints.filter(e => e.status !== 'success').length
    
    diagnosis.value = []
    if (bpjsErrors > 0 && baselineErrors > 0) {
      diagnosis.value.push("⚠️ General connectivity issues detected")
    } else if (bpjsErrors > 0 && baselineErrors === 0) {
      diagnosis.value.push("🔍 BPJS-specific issues detected")
    } else if (bpjsErrors === 0 && baselineErrors > 0) {
      diagnosis.value.push("🌐 Partial network issues (BPJS still accessible)")
    } else {
      diagnosis.value.push("✅ All systems operating normally")
    }
    
    recommendations.value = []
    if (bpjsErrors > 0 && baselineErrors > 0) {
      recommendations.value.push("🔧 Check your internet connection")
    } else if (bpjsErrors > 0) {
      recommendations.value.push("⏳ Wait for BPJS system recovery")
    } else {
      recommendations.value.push("✅ No action needed - all systems optimal")
    }
    
    timestamp.value = data.timestamp || new Date().toISOString()
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
    'success': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'error': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'warning': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'testing': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'pending': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
  }[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

const getResponseTimeColor = (time) => {
  if (!time) return 'text-gray-500 dark:text-gray-400'
  if (time < 200) return 'text-green-600 dark:text-green-400'
  if (time < 500) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-600 dark:text-red-400'
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

const formatDate = (isoString) => {
  if (!isoString) return ''
  return new Date(isoString).toLocaleString()
}

const addCustomEndpoint = () => {
  if (newEndpoint.value.name && newEndpoint.value.url) {
    customEndpoints.value.push({
      id: Date.now(),
      name: newEndpoint.value.name,
      url: newEndpoint.value.url,
      method: newEndpoint.value.method,
      headers: newEndpoint.value.headers,
      body: newEndpoint.value.body,
      status: 'pending',
      response_time: 0,
      code: '000'
    })
    
    // Reset form
    newEndpoint.value = {
      name: '',
      url: '',
      method: 'GET',
      headers: {},
      body: ''
    }
    showAddEndpointModal.value = false
    
    // Test the new endpoint immediately
    testCustomEndpoint(customEndpoints.value[customEndpoints.value.length - 1])
  }
}

const removeCustomEndpoint = (id) => {
  customEndpoints.value = customEndpoints.value.filter(endpoint => endpoint.id !== id)
}

const testCustomEndpoint = async (endpoint) => {
  try {
    endpoint.status = 'testing'
    const startTime = Date.now()
    
    const response = await axios({
      method: endpoint.method,
      url: endpoint.url,
      headers: endpoint.headers,
      data: endpoint.body && endpoint.method !== 'GET' ? endpoint.body : undefined,
      timeout: 10000
    })
    
    const endTime = Date.now()
    endpoint.response_time = endTime - startTime
    endpoint.status = 'success'
    endpoint.code = response.status.toString()
  } catch (error) {
    endpoint.status = 'error'
    endpoint.code = error.response?.status?.toString() || 'ERR'
    endpoint.response_time = 0
  }
}

// Initial fetch
onMounted(() => {
  fetchDiagnosticData()
  // Auto refresh every 30 seconds
  setInterval(fetchDiagnosticData, 30000)
})
</script>

<template>
    <Head title="Network Diagnostic Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                Network Diagnostic
                            </h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">
                                Real-time monitoring of BPJS endpoints and network performance
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <button @click="showAddEndpointModal = true" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Custom Endpoint
                            </button>
                            <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-300">Live</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDate(timestamp) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-gray-500 rounded-lg flex items-center justify-center">
                                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Endpoints</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ currentStatus.bpjs.length + currentStatus.baseline.length }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Success Rate</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ getSuccessRate() }}%
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Avg Response</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ getAverageResponseTime() }}ms
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Issues</dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ currentStatus.bpjs.filter(e => e.status !== 'success').length + currentStatus.baseline.filter(e => e.status !== 'success').length }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                    
                    <!-- BPJS Endpoints Panel -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">BPJS Endpoints</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Healthcare system monitoring</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div v-for="endpoint in currentStatus.bpjs" :key="endpoint.name" 
                                     class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ endpoint.name }}</h4>
                                            <span :class="getStatusBadgeClass(endpoint.status)" class="ml-3 px-2 py-1 text-xs font-medium rounded">
                                                {{ endpoint.code }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getEndpointDescription(endpoint.name) }}</p>
                                        <div class="flex items-center mt-2">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Response Time:</span>
                                            <span :class="getResponseTimeColor(endpoint.response_time)" class="ml-2 text-xs font-medium">
                                                {{ endpoint.response_time }}ms
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="endpoint.status === 'success' ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900'">
                                            <svg v-if="endpoint.status === 'success'" class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Baseline Endpoints Panel -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Baseline Endpoints</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Network reference monitoring</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div v-for="endpoint in currentStatus.baseline" :key="endpoint.name" 
                                     class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ endpoint.name }}</h4>
                                            <span :class="getStatusBadgeClass(endpoint.status)" class="ml-3 px-2 py-1 text-xs font-medium rounded">
                                                {{ endpoint.code }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getEndpointDescription(endpoint.name) }}</p>
                                        <div class="flex items-center mt-2">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Response Time:</span>
                                            <span :class="getResponseTimeColor(endpoint.response_time)" class="ml-2 text-xs font-medium">
                                                {{ endpoint.response_time }}ms
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="endpoint.status === 'success' ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900'">
                                            <svg v-if="endpoint.status === 'success'" class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Endpoints Panel -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Custom Endpoints</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">User-defined endpoint monitoring</p>
                                </div>
                                <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded-full">
                                    {{ customEndpoints.length }} endpoints
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="customEndpoints.length === 0" class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <h4 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No custom endpoints</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first custom endpoint.</p>
                                <button @click="showAddEndpointModal = true" 
                                        class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                                    Add Custom Endpoint
                                </button>
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="endpoint in customEndpoints" :key="endpoint.id" 
                                     class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ endpoint.name }}</h4>
                                            <span :class="getStatusBadgeClass(endpoint.status)" class="ml-3 px-2 py-1 text-xs font-medium rounded">
                                                {{ endpoint.code }}
                                            </span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded">
                                                {{ endpoint.method }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ endpoint.url }}</p>
                                        <div class="flex items-center mt-2">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Response Time:</span>
                                            <span :class="getResponseTimeColor(endpoint.response_time)" class="ml-2 text-xs font-medium">
                                                {{ endpoint.response_time }}ms
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button @click="testCustomEndpoint(endpoint)" 
                                                class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" 
                                                title="Test endpoint">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </button>
                                        <button @click="removeCustomEndpoint(endpoint.id)" 
                                                class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                                title="Remove endpoint">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="endpoint.status === 'success' ? 'bg-green-100 dark:bg-green-900' : endpoint.status === 'testing' ? 'bg-blue-100 dark:bg-blue-900' : 'bg-red-100 dark:bg-red-900'">
                                            <svg v-if="endpoint.status === 'success'" class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else-if="endpoint.status === 'testing'" class="w-4 h-4 text-blue-600 dark:text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Latency Analysis -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Latency Analysis</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Performance comparison metrics</p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-6 mb-6">
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">BPJS Average</div>
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ latencyComparison.bpjs_avg || 0 }}ms
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Baseline Average</div>
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ latencyComparison.baseline_avg || 0 }}ms
                                    </div>
                                </div>
                            </div>
                            <div class="text-center pt-6 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Performance Difference</div>
                                <div class="flex items-center justify-center space-x-4">
                                    <div class="text-lg font-semibold" :class="(latencyComparison.difference || 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                        {{ (latencyComparison.difference || 0) > 0 ? '+' : '' }}{{ latencyComparison.difference || 0 }}ms
                                    </div>
                                    <span class="px-2 py-1 rounded text-xs font-medium" :class="(latencyComparison.ratio || 1) <= 1.5 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : (latencyComparison.ratio || 1) <= 2 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'">
                                        {{ (latencyComparison.ratio || 1) <= 1.5 ? 'Excellent' : (latencyComparison.ratio || 1) <= 2 ? 'Good' : 'Needs Attention' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Network Diagnosis -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Network Diagnosis</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">System analysis and recommendations</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Current Diagnosis -->
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Current Status</h4>
                                    <div class="space-y-2">
                                        <div v-for="(item, index) in diagnosis" :key="index" 
                                             class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex-shrink-0 w-5 h-5 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mt-0.5">
                                                <div class="w-2 h-2 bg-blue-600 dark:bg-blue-400 rounded-full"></div>
                                            </div>
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ item }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recommendations -->
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Recommendations</h4>
                                    <div class="space-y-2">
                                        <div v-for="(item, index) in recommendations" :key="index" 
                                             class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex-shrink-0 w-5 h-5 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5">
                                                <div class="w-2 h-2 bg-green-600 dark:bg-green-400 rounded-full"></div>
                                            </div>
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ item }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Add Custom Endpoint Modal -->
        <div v-if="showAddEndpointModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Custom Endpoint</h3>
                        <button @click="showAddEndpointModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="addCustomEndpoint" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input v-model="newEndpoint.name" type="text" required 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="e.g., My API Endpoint">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL</label>
                            <input v-model="newEndpoint.url" type="url" required 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="https://api.example.com/health">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Method</label>
                            <select v-model="newEndpoint.method" 
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="PATCH">PATCH</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="showAddEndpointModal = false" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                                Add Endpoint
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

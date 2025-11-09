<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Activity, Signal, Clock, ShieldCheck, AlertTriangle, Plus, RefreshCw } from 'lucide-vue-next';

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
    
    // Handle PING method with HEAD request and fallback to GET
    if (endpoint.method === 'PING') {
      let response
      try {
        response = await axios.head(endpoint.url, {
          headers: endpoint.headers,
          timeout: 10000
        })
      } catch (headErr) {
        response = await axios.get(endpoint.url, {
          headers: endpoint.headers,
          timeout: 10000
        })
      }
      const endTime = Date.now()
      endpoint.response_time = endTime - startTime
      const statusCode = response.status
      endpoint.code = statusCode.toString()
      endpoint.status = statusCode >= 200 && statusCode < 400 ? 'success' : 'error'
      return
    }

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
                
                <!-- Hero Header -->
                <div class="mb-8">
                    <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-900 dark:to-gray-800">
                        <div class="p-6 md:p-8">
                            <div class="flex items-center justify-between">
                                <div class="space-y-2">
                                    <div class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-200">
                                        <Signal class="mr-2 h-3.5 w-3.5" />
                                        Live Monitoring
                                    </div>
                                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
                                        Network Diagnostic
                                    </h1>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Modern dashboard for BPJS connectivity and network performance
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Button @click="showAddEndpointModal = true" size="sm">
                                        <Plus class="w-4 h-4 mr-2" />
                                        Add Endpoint
                                    </Button>
                                    <div class="hidden md:flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2">
                                        <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Live</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(timestamp) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <Card class="shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <Activity class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Endpoints</CardTitle>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                                        {{ currentStatus.bpjs.length + currentStatus.baseline.length }}
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>

                    <Card class="shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                                    <ShieldCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-medium text-gray-500 dark:text-gray-400">Success Rate</CardTitle>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                                        {{ getSuccessRate() }}%
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>

                    <Card class="shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                                    <Clock class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Response</CardTitle>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                                        {{ getAverageResponseTime() }}ms
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>

                    <Card class="shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                                    <AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Issues</CardTitle>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-white">
                                        {{ currentStatus.bpjs.filter(e => e.status !== 'success').length + currentStatus.baseline.filter(e => e.status !== 'success').length }}
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                    
                    <!-- BPJS Endpoints Panel -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <CardTitle>BPJS Endpoints</CardTitle>
                            <CardDescription>Healthcare system monitoring</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="endpoint in currentStatus.bpjs" :key="endpoint.name" 
                                     class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
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
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="endpoint.status === 'success' ? 'bg-emerald-100 dark:bg-emerald-900' : 'bg-red-100 dark:bg-red-900'">
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
                        </CardContent>
                    </Card>

                    <!-- Baseline Endpoints Panel -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <CardTitle>Baseline Endpoints</CardTitle>
                            <CardDescription>Network reference monitoring</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="endpoint in currentStatus.baseline" :key="endpoint.name" 
                                     class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
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
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="endpoint.status === 'success' ? 'bg-emerald-100 dark:bg-emerald-900' : 'bg-red-100 dark:bg-red-900'">
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
                        </CardContent>
                    </Card>

                    <!-- Custom Endpoints Panel -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Custom Endpoints</CardTitle>
                                    <CardDescription>User-defined endpoint monitoring</CardDescription>
                                </div>
                                <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded-full">
                                    {{ customEndpoints.length }} endpoints
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="customEndpoints.length === 0" class="text-center py-8">
                                <Plus class="mx-auto h-12 w-12 text-gray-400" />
                                <h4 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No custom endpoints</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first custom endpoint.</p>
                                <Button class="mt-4" @click="showAddEndpointModal = true">
                                    <Plus class="w-4 h-4 mr-2" />
                                    Add Custom Endpoint
                                </Button>
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="endpoint in customEndpoints" :key="endpoint.id" 
                                     class="flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
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
                                        <Button @click="testCustomEndpoint(endpoint)" variant="ghost" size="sm" title="Test endpoint">
                                            <RefreshCw class="w-4 h-4" />
                                        </Button>
                                        <Button @click="removeCustomEndpoint(endpoint.id)" variant="ghost" size="sm" class="text-red-600" title="Remove endpoint">
                                            <AlertTriangle class="w-4 h-4" />
                                        </Button>
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
                        </CardContent>
                    </Card>
                </div>

                <!-- Performance Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Latency Analysis -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <CardTitle>Latency Analysis</CardTitle>
                            <CardDescription>Performance comparison metrics</CardDescription>
                        </CardHeader>
                        <CardContent>
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
                        </CardContent>
                    </Card>

                    <!-- Network Diagnosis -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <CardTitle>Network Diagnosis</CardTitle>
                            <CardDescription>System analysis and recommendations</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-6">
                                <!-- Current Diagnosis -->
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Current Status</h4>
                                    <div class="space-y-2">
                                        <div v-for="(item, index) in diagnosis" :key="index" 
                                             class="flex items-start space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
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
                                             class="flex items-start space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div class="flex-shrink-0 w-5 h-5 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5">
                                                <div class="w-2 h-2 bg-green-600 dark:bg-green-400 rounded-full"></div>
                                            </div>
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ item }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>
        </div>

        <!-- Add Custom Endpoint Modal -->
        <div v-if="showAddEndpointModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="showAddEndpointModal = false">
            <div class="relative top-20 mx-auto w-full max-w-md px-4">
                <Card class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Add Custom Endpoint</CardTitle>
                            <CardDescription>Quickly add an endpoint for testing</CardDescription>
                        </div>
                        <Button variant="ghost" size="icon" @click="showAddEndpointModal = false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </Button>
                    </CardHeader>
                    <CardContent>
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
                                    <option value="PING">PING</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                            </div>
                            <div class="flex justify-end space-x-3 pt-2">
                                <Button type="button" variant="outline" @click="showAddEndpointModal = false">Cancel</Button>
                                <Button type="submit">Add Endpoint</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { 
  Activity, 
  CheckCircle, 
  XCircle, 
  Clock, 
  RefreshCw,
  Wifi,
  Zap,
  AlertTriangle,
  Plus,
  Settings,
  Trash2,
  Edit
} from 'lucide-vue-next';

interface CustomEndpoint {
  id: string;
  name: string;
  url: string;
  description: string;
  method?: 'GET' | 'POST';
  headers?: Record<string, string>;
  timeout?: number;
  isActive: boolean;
  isBpjsEndpoint?: boolean; // Flag untuk BPJS endpoints
  useProxy?: boolean; // Flag untuk use backend proxy
}

interface EndpointData {
  name: string;
  url: string;
  response_time: number;
  code: string | number;
  message: string;
  status: 'success' | 'error' | 'timeout';
  severity?: string;
  description?: string;
  isCustom?: boolean;
  customId?: string;
}

interface Alert {
  id: number;
  endpoint_name: string;
  type: string;
  message: string;
  triggered_at: string;
}

interface SummaryData {
  total: number;
  success: number;
  error: number;
  avg_response_time: number;
  uptime_percentage: number;
  uptime_24h?: number;
  avg_response_time_24h?: number;
}

interface MonitoringData {
  summary: SummaryData;
  endpoints: EndpointData[];
  alerts?: Alert[];
  timestamp: string;
}

const breadcrumbs = [
  {
    title: 'BPJS Monitoring',
    href: '/bpjs-monitoring',
  },
];

const monitoringData = ref<MonitoringData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const lastUpdate = ref<string>('');
const customEndpoints = ref<CustomEndpoint[]>([]);
const showAddEndpointModal = ref(false);
const showManageEndpointsModal = ref(false);
const editingEndpoint = ref<CustomEndpoint | null>(null);

// Form data for new endpoint
const newEndpoint = ref<Partial<CustomEndpoint>>({
  name: '',
  url: '',
  description: '',
  method: 'GET',
  headers: {},
  timeout: 10,
  isActive: true
});

let intervalId: number | null = null;

// LocalStorage functions
const saveCustomEndpoints = () => {
  localStorage.setItem('bpjs_custom_endpoints', JSON.stringify(customEndpoints.value));
};

const loadCustomEndpoints = () => {
  const stored = localStorage.getItem('bpjs_custom_endpoints');
  if (stored) {
    try {
      customEndpoints.value = JSON.parse(stored);
    } catch (e) {
      console.error('Error loading custom endpoints:', e);
      customEndpoints.value = [];
    }
  }
};

const addCustomEndpoint = () => {
  if (!newEndpoint.value.name || !newEndpoint.value.url) {
    error.value = 'Name and URL are required';
    return;
  }

  // Detect if this is a BPJS endpoint
  const isBpjsEndpoint = newEndpoint.value.url.includes('apijkn.bpjs-kesehatan.go.id') || 
                        newEndpoint.value.url.includes('bpjs-kesehatan.go.id');

  const endpoint: CustomEndpoint = {
    id: Date.now().toString(),
    name: newEndpoint.value.name,
    url: newEndpoint.value.url,
    description: newEndpoint.value.description || '',
    method: newEndpoint.value.method || 'GET',
    headers: newEndpoint.value.headers || {},
    timeout: newEndpoint.value.timeout || 10,
    isActive: newEndpoint.value.isActive !== false,
    isBpjsEndpoint: isBpjsEndpoint,
    useProxy: isBpjsEndpoint // Auto-enable proxy for BPJS endpoints
  };

  customEndpoints.value.push(endpoint);
  saveCustomEndpoints();
  
  // Show warning for BPJS endpoints
  if (isBpjsEndpoint) {
    error.value = 'BPJS endpoint detected! This will be tested via backend proxy with proper authentication.';
    setTimeout(() => { error.value = null; }, 5000);
  }
  
  // Reset form
  newEndpoint.value = {
    name: '',
    url: '',
    description: '',
    method: 'GET',
    headers: {},
    timeout: 10,
    isActive: true
  };
  
  showAddEndpointModal.value = false;
  
  // Refresh monitoring data to include new endpoint
  fetchMonitoringData();
};

const editCustomEndpoint = (endpoint: CustomEndpoint) => {
  editingEndpoint.value = { ...endpoint };
  newEndpoint.value = { ...endpoint };
  showAddEndpointModal.value = true;
};

const updateCustomEndpoint = () => {
  if (!editingEndpoint.value || !newEndpoint.value.name || !newEndpoint.value.url) {
    error.value = 'Name and URL are required';
    return;
  }

  const index = customEndpoints.value.findIndex(ep => ep.id === editingEndpoint.value!.id);
  if (index !== -1) {
    customEndpoints.value[index] = {
      ...editingEndpoint.value,
      name: newEndpoint.value.name,
      url: newEndpoint.value.url,
      description: newEndpoint.value.description || '',
      method: newEndpoint.value.method || 'GET',
      headers: newEndpoint.value.headers || {},
      timeout: newEndpoint.value.timeout || 10,
      isActive: newEndpoint.value.isActive !== false
    };
    
    saveCustomEndpoints();
    closeAddEndpointModal();
    fetchMonitoringData();
  }
};

const deleteCustomEndpoint = (id: string) => {
  if (confirm('Are you sure you want to delete this endpoint?')) {
    customEndpoints.value = customEndpoints.value.filter(ep => ep.id !== id);
    saveCustomEndpoints();
    fetchMonitoringData();
  }
};

const toggleEndpointStatus = (id: string) => {
  const endpoint = customEndpoints.value.find(ep => ep.id === id);
  if (endpoint) {
    endpoint.isActive = !endpoint.isActive;
    saveCustomEndpoints();
    fetchMonitoringData();
  }
};

const closeAddEndpointModal = () => {
  showAddEndpointModal.value = false;
  editingEndpoint.value = null;
  newEndpoint.value = {
    name: '',
    url: '',
    description: '',
    method: 'GET',
    headers: {},
    timeout: 10,
    isActive: true
  };
  error.value = null;
};

// Test custom endpoint
const testCustomEndpoint = async (endpoint: CustomEndpoint): Promise<EndpointData> => {
  const start = performance.now();
  
  try {
    let response;
    
    // Use backend proxy for BPJS endpoints
    if (endpoint.isBpjsEndpoint && endpoint.useProxy) {
      // Test via backend with proper BPJS authentication
      response = await fetch('/bpjs-monitoring/test-custom-endpoint', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          url: endpoint.url,
          method: endpoint.method || 'GET',
          timeout: endpoint.timeout || 10
        }),
        signal: AbortSignal.timeout((endpoint.timeout || 10) * 1000 + 5000) // Extra time for backend
      });
      
      if (!response.ok) {
        throw new Error(`Backend proxy error: ${response.status}`);
      }
      
      const proxyResult = await response.json();
      const end = performance.now();
      const response_time = Math.round(end - start);
      
      return {
        name: endpoint.name,
        url: endpoint.url,
        response_time: proxyResult.response_time || response_time,
        code: proxyResult.code || response.status,
        message: proxyResult.message || 'Backend proxy response',
        status: proxyResult.status || (response.ok ? 'success' : 'error'),
        severity: proxyResult.severity || getSeverityFromResponseTime(response_time),
        description: endpoint.description,
        isCustom: true,
        customId: endpoint.id
      };
    } else {
      // Direct browser request for non-BPJS endpoints
      response = await fetch(endpoint.url, {
        method: endpoint.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...endpoint.headers
        },
        signal: AbortSignal.timeout((endpoint.timeout || 10) * 1000)
      });

      const end = performance.now();
      const response_time = Math.round(end - start);
      
      return {
        name: endpoint.name,
        url: endpoint.url,
        response_time,
        code: response.status,
        message: response.statusText || 'OK',
        status: response.ok ? 'success' : 'error',
        severity: getSeverityFromResponseTime(response_time),
        description: endpoint.description,
        isCustom: true,
        customId: endpoint.id
      };
    }
    
  } catch (error: any) {
    const end = performance.now();
    const response_time = Math.round(end - start);
    
    // Better error handling for common issues
    let message = 'Network error';
    let status: 'error' | 'timeout' = 'error';
    
    if (error.name === 'TimeoutError') {
      message = 'Request timeout';
      status = 'timeout';
    } else if (error.message?.includes('CORS')) {
      message = 'CORS policy blocked (try enabling proxy for BPJS endpoints)';
    } else if (error.message?.includes('Failed to fetch')) {
      message = 'Network unreachable or CORS blocked';
    } else {
      message = error.message || 'Unknown error';
    }
    
    return {
      name: endpoint.name,
      url: endpoint.url,
      response_time,
      code: 'ERROR',
      message,
      status,
      severity: 'critical',
      description: endpoint.description,
      isCustom: true,
      customId: endpoint.id
    };
  }
};

// Helper function to determine severity from response time
const getSeverityFromResponseTime = (responseTime: number): string => {
  if (responseTime >= 2000) return 'critical';
  if (responseTime >= 1000) return 'slow';  
  if (responseTime >= 500) return 'good';
  return 'excellent';
};

const fetchMonitoringData = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    // Fetch default BPJS endpoints using the correct endpoint
    const response = await fetch('/bpjs-monitoring/data');
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      throw new Error(`Expected JSON, got: ${contentType}. Response: ${text.substring(0, 200)}...`);
    }
    
    const data = await response.json();
    
    // Test custom endpoints and add to the results
    const customResults: EndpointData[] = [];
    const activeCustomEndpoints = customEndpoints.value.filter(ep => ep.isActive);
    
    if (activeCustomEndpoints.length > 0) {
      const customTests = await Promise.allSettled(
        activeCustomEndpoints.map(endpoint => testCustomEndpoint(endpoint))
      );
      
      customTests.forEach((result, index) => {
        if (result.status === 'fulfilled') {
          customResults.push(result.value);
        } else {
          // Handle failed custom endpoint test
          const endpoint = activeCustomEndpoints[index];
          customResults.push({
            name: endpoint.name,
            url: endpoint.url,
            response_time: 0,
            code: 'ERROR',
            message: 'Test failed: ' + result.reason?.message || 'Unknown error',
            status: 'error',
            severity: 'critical',
            description: endpoint.description,
            isCustom: true,
            customId: endpoint.id
          });
        }
      });
    }
    
    // Combine default and custom endpoints
    const allEndpoints = [...data.endpoints, ...customResults];
    
    // Recalculate summary with custom endpoints included
    const totalEndpoints = allEndpoints.length;
    const successfulEndpoints = allEndpoints.filter(ep => ep.status === 'success').length;
    const failedEndpoints = totalEndpoints - successfulEndpoints;
    const totalResponseTime = allEndpoints.reduce((sum, ep) => sum + ep.response_time, 0);
    const avgResponseTime = totalEndpoints > 0 ? totalResponseTime / totalEndpoints : 0;
    const uptimePercentage = totalEndpoints > 0 ? (successfulEndpoints / totalEndpoints) * 100 : 0;
    
    monitoringData.value = {
      ...data,
      summary: {
        ...data.summary,
        total: totalEndpoints,
        success: successfulEndpoints,
        error: failedEndpoints,
        avg_response_time: Math.round(avgResponseTime * 100) / 100,
        uptime_percentage: Math.round(uptimePercentage * 100) / 100
      },
      endpoints: allEndpoints
    };
    
    lastUpdate.value = data.timestamp;
    
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'An error occurred';
    console.error('Error fetching monitoring data:', err);
  } finally {
    isLoading.value = false;
  }
};

const getStatusColor = (status: string) => {
  switch (status) {
    case 'success': return 'bg-green-500';
    case 'timeout': return 'bg-yellow-500';
    case 'error': return 'bg-red-500';
    default: return 'bg-gray-500';
  }
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'success': return CheckCircle;
    case 'timeout': return Clock;
    case 'error': return XCircle;
    default: return Clock;
  }
};

const getResponseTimeColor = (responseTime: number) => {
  if (responseTime < 1000) return 'text-green-600';
  if (responseTime < 2000) return 'text-yellow-600';
  return 'text-red-600';
};

const formatTime = (timeString: string) => {
  return new Date(timeString).toLocaleTimeString();
};

const getBadgeClass = (status: string) => {
  switch (status) {
    case 'success':
      return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
    case 'timeout':
      return 'inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800';
    case 'error':
      return 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800';
    default:
      return 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800';
  }
};

onMounted(() => {
  loadCustomEndpoints();
  fetchMonitoringData();
  intervalId = window.setInterval(() => {
    fetchMonitoringData();
  }, 30000);
});

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
});
</script>

<template>
  <Head title="BPJS Monitoring Dashboard" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            BPJS API Monitoring
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Real-time monitoring dashboard untuk konektivitas API BPJS
          </p>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            Last updated: {{ lastUpdate }}
          </div>
          <Button 
            @click="showAddEndpointModal = true" 
            variant="default"
            size="sm"
          >
            <Plus class="w-4 h-4 mr-2" />
            Add Endpoint
          </Button>
          <Button 
            @click="showManageEndpointsModal = true" 
            variant="outline"
            size="sm"
          >
            <Settings class="w-4 h-4 mr-2" />
            Manage ({{ customEndpoints.length }})
          </Button>
          <Button 
            @click="fetchMonitoringData" 
            :disabled="isLoading"
            variant="outline"
            size="sm"
          >
            <RefreshCw :class="{ 'animate-spin': isLoading }" class="w-4 h-4 mr-2" />
            Refresh
          </Button>
        </div>
      </div>

      <!-- Alert Banner -->
      <div v-if="monitoringData?.alerts && monitoringData.alerts.length > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-start">
          <AlertTriangle class="h-5 w-5 text-red-500 mr-3 mt-0.5 flex-shrink-0" />
          <div class="flex-1">
            <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
              Active Alerts ({{ monitoringData.alerts.length }})
            </h3>
            <div class="mt-2 text-sm text-red-700 dark:text-red-400 space-y-1">
              <div v-for="alert in monitoringData.alerts" :key="alert.id">
                <strong>{{ alert.endpoint_name }}</strong>: {{ alert.message }}
                <span class="text-xs text-red-500 ml-2">{{ formatTime(alert.triggered_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center">
          <XCircle class="w-5 h-5 text-red-500 mr-2" />
          <span class="text-red-700 dark:text-red-300">{{ error }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading && !monitoringData" class="flex items-center justify-center h-64">
        <div class="text-center">
          <RefreshCw class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-500" />
          <p class="text-gray-600 dark:text-gray-400">Loading monitoring data...</p>
        </div>
      </div>

      <!-- Main Content -->
      <div v-if="monitoringData" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Total Endpoints -->
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Endpoints</CardTitle>
              <Activity class="h-4 w-4 text-blue-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ monitoringData.summary.total }}</div>
              <p class="text-xs text-muted-foreground mt-1">Monitored endpoints</p>
            </CardContent>
          </Card>

          <!-- Success Rate -->
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Uptime</CardTitle>
              <Wifi class="h-4 w-4 text-green-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-green-600">
                {{ monitoringData.summary.uptime_percentage }}%
              </div>
              <div class="text-xs text-muted-foreground mt-1">
                {{ monitoringData.summary.uptime_24h ? `24h: ${monitoringData.summary.uptime_24h}%` : 'Current status' }}
              </div>
            </CardContent>
          </Card>

          <!-- Average Response Time -->
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Avg Response Time</CardTitle>
              <Zap class="h-4 w-4 text-yellow-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold" :class="getResponseTimeColor(monitoringData.summary.avg_response_time)">
                {{ Math.round(monitoringData.summary.avg_response_time) }} ms
              </div>
              <div class="text-xs text-muted-foreground mt-1">
                {{ monitoringData.summary.avg_response_time_24h ? `24h: ${Math.round(monitoringData.summary.avg_response_time_24h)}ms` : 'Current average' }}
              </div>
            </CardContent>
          </Card>

          <!-- Status Summary -->
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Status</CardTitle>
              <Activity class="h-4 w-4 text-gray-500" />
            </CardHeader>
            <CardContent>
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <CheckCircle class="w-4 h-4 text-green-500 mr-1" />
                  <span class="text-sm font-medium">{{ monitoringData.summary.success }}</span>
                </div>
                <div class="flex items-center">
                  <XCircle class="w-4 h-4 text-red-500 mr-1" />
                  <span class="text-sm font-medium">{{ monitoringData.summary.error }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Endpoints Table -->
        <Card>
          <CardHeader>
            <CardTitle>Endpoint Status</CardTitle>
            <CardDescription>Real-time status of all monitored endpoints</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div 
                v-for="endpoint in monitoringData.endpoints" 
                :key="endpoint.name"
                class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
              >
                <div class="flex items-center space-x-4 flex-1">
                  <div class="flex items-center">
                    <div :class="getStatusColor(endpoint.status)" class="w-3 h-3 rounded-full mr-3"></div>
                    <component :is="getStatusIcon(endpoint.status)" class="w-4 h-4 mr-2" :class="{
                      'text-green-500': endpoint.status === 'success',
                      'text-yellow-500': endpoint.status === 'timeout',
                      'text-red-500': endpoint.status === 'error'
                    }" />
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center space-x-2">
                      <div class="font-medium text-gray-900 dark:text-white">
                        {{ endpoint.name }}
                      </div>
                      <span v-if="endpoint.isCustom" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                        Custom
                      </span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-md">
                      {{ endpoint.description || endpoint.url }}
                    </div>
                  </div>
                </div>
                
                <div class="flex items-center space-x-4">
                  <!-- Custom endpoint actions -->
                  <div v-if="endpoint.isCustom" class="flex items-center space-x-2">
                    <Button 
                      @click="editCustomEndpoint(customEndpoints.find(ep => ep.id === endpoint.customId)!)"
                      variant="ghost"
                      size="sm"
                      class="h-8 w-8 p-0"
                    >
                      <Edit class="h-3 w-3" />
                    </Button>
                    <Button 
                      @click="deleteCustomEndpoint(endpoint.customId!)"
                      variant="ghost"
                      size="sm"
                      class="h-8 w-8 p-0 text-red-500 hover:text-red-700"
                    >
                      <Trash2 class="h-3 w-3" />
                    </Button>
                  </div>
                  
                  <div class="text-right">
                    <div class="font-medium" :class="getResponseTimeColor(endpoint.response_time)">
                      {{ Math.round(endpoint.response_time) }} ms
                    </div>
                    <div class="flex items-center space-x-2 mt-1">
                      <span :class="getBadgeClass(endpoint.status)">
                        {{ endpoint.code }}
                      </span>
                      <span 
                        v-if="endpoint.severity" 
                        :class="['text-xs px-2 py-1 rounded-full font-medium', 
                                 endpoint.severity === 'excellent' ? 'text-green-600 bg-green-100' :
                                 endpoint.severity === 'good' ? 'text-blue-600 bg-blue-100' :
                                 endpoint.severity === 'slow' ? 'text-yellow-600 bg-yellow-100' :
                                 'text-red-600 bg-red-100']"
                      >
                        {{ endpoint.severity }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Add/Edit Endpoint Modal -->
    <div v-if="showAddEndpointModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ editingEndpoint ? 'Edit Endpoint' : 'Add Custom Endpoint' }}
            </h3>
            <Button @click="closeAddEndpointModal" variant="ghost" size="sm" class="h-8 w-8 p-0">
              <XCircle class="h-4 w-4" />
            </Button>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Name *
              </label>
              <input 
                v-model="newEndpoint.name"
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Enter endpoint name"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                URL *
              </label>
              <input 
                v-model="newEndpoint.url"
                type="url" 
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="https://api.example.com/endpoint"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Description
              </label>
              <textarea 
                v-model="newEndpoint.description"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                rows="2"
                placeholder="Optional description"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Method
                </label>
                <select 
                  v-model="newEndpoint.method"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                >
                  <option value="GET">GET</option>
                  <option value="POST">POST</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Timeout (s)
                </label>
                <input 
                  v-model.number="newEndpoint.timeout"
                  type="number" 
                  min="1"
                  max="60"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                />
              </div>
            </div>

            <div class="flex items-center">
              <input 
                v-model="newEndpoint.isActive"
                type="checkbox" 
                id="isActive"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label for="isActive" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                Active (include in monitoring)
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-3 mt-6">
            <Button @click="closeAddEndpointModal" variant="outline">
              Cancel
            </Button>
            <Button @click="editingEndpoint ? updateCustomEndpoint() : addCustomEndpoint()" variant="default">
              {{ editingEndpoint ? 'Update' : 'Add' }} Endpoint
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Manage Endpoints Modal -->
    <div v-if="showManageEndpointsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              Manage Custom Endpoints ({{ customEndpoints.length }})
            </h3>
            <Button @click="showManageEndpointsModal = false" variant="ghost" size="sm" class="h-8 w-8 p-0">
              <XCircle class="h-4 w-4" />
            </Button>
          </div>

          <div v-if="customEndpoints.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No custom endpoints configured.</p>
            <Button @click="showManageEndpointsModal = false; showAddEndpointModal = true" class="mt-4">
              <Plus class="w-4 h-4 mr-2" />
              Add First Endpoint
            </Button>
          </div>

          <div v-else class="space-y-3">
            <div 
              v-for="endpoint in customEndpoints" 
              :key="endpoint.id"
              class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg"
            >
              <div class="flex-1">
                <div class="flex items-center space-x-2">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ endpoint.name }}
                  </div>
                  <span 
                    :class="[
                      'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                      endpoint.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    ]"
                  >
                    {{ endpoint.isActive ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  {{ endpoint.method || 'GET' }} {{ endpoint.url }}
                </div>
                <div v-if="endpoint.description" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  {{ endpoint.description }}
                </div>
              </div>
              
              <div class="flex items-center space-x-2">
                <Button 
                  @click="toggleEndpointStatus(endpoint.id)"
                  variant="ghost"
                  size="sm"
                  :class="endpoint.isActive ? 'text-yellow-600' : 'text-green-600'"
                >
                  {{ endpoint.isActive ? 'Disable' : 'Enable' }}
                </Button>
                <Button 
                  @click="editCustomEndpoint(endpoint)"
                  variant="ghost"
                  size="sm"
                >
                  <Edit class="h-4 w-4" />
                </Button>
                <Button 
                  @click="deleteCustomEndpoint(endpoint.id)"
                  variant="ghost"
                  size="sm"
                  class="text-red-500 hover:text-red-700"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <div class="flex justify-between mt-6">
            <Button @click="showAddEndpointModal = true" variant="outline">
              <Plus class="w-4 h-4 mr-2" />
              Add New
            </Button>
            <Button @click="showManageEndpointsModal = false" variant="default">
              Done
            </Button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

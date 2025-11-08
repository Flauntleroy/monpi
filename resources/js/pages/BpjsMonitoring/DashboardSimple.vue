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
  AlertTriangle
} from 'lucide-vue-next';

interface EndpointData {
  name: string;
  url: string;
  response_time: number;
  code: string | number;
  message: string;
  status: 'success' | 'error' | 'timeout';
  severity?: string;
  description?: string;
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
let intervalId: number | null = null;

const fetchMonitoringData = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    // Try the simple endpoint first for testing
    const response = await fetch('/simple-bpjs-data');
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      throw new Error(`Expected JSON, got: ${contentType}. Response: ${text.substring(0, 200)}...`);
    }
    
    const data = await response.json();
    monitoringData.value = data;
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
                    <div class="font-medium text-gray-900 dark:text-white">
                      {{ endpoint.name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-md">
                      {{ endpoint.description || endpoint.url }}
                    </div>
                  </div>
                </div>
                
                <div class="flex items-center space-x-4">
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
  </AppLayout>
</template>

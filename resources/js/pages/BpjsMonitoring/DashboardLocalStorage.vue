<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import FloatingThemeToggle from '@/components/FloatingThemeToggle.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import VueApexCharts from 'vue3-apexcharts';
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
  Edit,
  Shield,
  MoreVertical
} from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu'

interface CustomEndpoint {
  id: string;
  name: string;
  url: string;
  description: string;
  method?: 'GET' | 'POST' | 'PING';
  headers?: Record<string, string>;
  timeout?: number;
  isActive: boolean;
  isBpjsEndpoint?: boolean;
  useProxy?: boolean;
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

interface DiagnosticTest {
  host?: string;
  ip?: string;
  domain?: string;
  resolved_ip?: string;
  name?: string;
  url?: string;
  type?: string;
  status: 'success' | 'error' | 'warning';
  response_time: number;
  http_code?: number;
  error?: string;
  response_size?: number;
}

interface DiagnosticCategory {
  tests: DiagnosticTest[];
  overall_status: 'good' | 'warning' | 'critical';
}

interface DiagnosticAnalysis {
  root_cause: string;
  confidence: number;
  recommendations: string[];
  summary: string;
}

interface NetworkDiagnostics {
  timestamp: string;
  local_connectivity: DiagnosticCategory;
  dns_resolution: DiagnosticCategory;
  external_connectivity: DiagnosticCategory;
  bpjs_infrastructure: DiagnosticCategory;
  analysis: DiagnosticAnalysis;
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
  network_diagnostics?: NetworkDiagnostics;
  timestamp: string;
}

interface HistoricalDataPoint {
  timestamp: string;
  summary: SummaryData;
  endpoints: EndpointData[];
}

const breadcrumbs = [
  {
    title: 'BPJS Monitoring',
    href: '/bpjs-monitoring',
  },
];

const monitoringData = ref<MonitoringData | null>(null);
const historicalData = ref<HistoricalDataPoint[]>([]);
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

// LocalStorage functions for historical data
const saveHistoricalData = (data: MonitoringData) => {
  const historical = getHistoricalData();
  const newDataPoint: HistoricalDataPoint = {
    timestamp: data.timestamp,
    summary: data.summary,
    endpoints: data.endpoints
  };
  
  historical.push(newDataPoint);
  
  // Keep only last 24 hours (assuming 30-second intervals = 2880 data points)
  const maxDataPoints = 2880;
  if (historical.length > maxDataPoints) {
    historical.splice(0, historical.length - maxDataPoints);
  }
  
  localStorage.setItem('bpjs_historical_data', JSON.stringify(historical));
  historicalData.value = historical;
};

const getHistoricalData = (): HistoricalDataPoint[] => {
  const stored = localStorage.getItem('bpjs_historical_data');
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (e) {
      console.error('Error loading historical data:', e);
      return [];
    }
  }
  return [];
};

// LocalStorage functions for custom endpoints
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

// Normalize common BPJS URL typos (e.g., .go.ids → .go.id)
const normalizeBpjsUrl = (url: string) => {
  const trimmed = (url || '').trim();
  let normalized = trimmed;
  let corrected = false;

  const corrections: Array<[string, string]> = [
    ['apijkn.bpjs-kesehatan.go.ids', 'apijkn.bpjs-kesehatan.go.id'],
    ['bpjs-kesehatan.go.ids', 'bpjs-kesehatan.go.id']
  ];

  corrections.forEach(([bad, good]) => {
    if (normalized.includes(bad)) {
      normalized = normalized.replace(bad, good);
      corrected = true;
    }
  });

  return { url: normalized, corrected };
};

const addCustomEndpoint = () => {
  // Normalize then detect BPJS endpoints across known domains and subdomains
  const normalized = normalizeBpjsUrl(newEndpoint.value.url || '');
  if (normalized.corrected) {
    error.value = 'Memperbaiki URL: mengganti domain .go.ids menjadi .go.id';
    setTimeout(() => { error.value = null; }, 5000);
  }
  const urlStr = normalized.url;
  const isBpjsEndpoint = urlStr.includes('bpjs-kesehatan.go.id') ||
                         urlStr.includes('apijkn.bpjs-kesehatan.go.id') ||
                         urlStr.includes('new-api.bpjs-kesehatan.go.id') ||
                         // Be forgiving to common typos like .go.ids so they still route via proxy
                         urlStr.includes('bpjs-kesehatan.go.') ||
                         urlStr.includes('apijkn.bpjs-kesehatan.go.');

  const endpoint: CustomEndpoint = {
    id: Date.now().toString(),
    name: newEndpoint.value.name || '',
    url: urlStr,
    description: newEndpoint.value.description || '',
    method: newEndpoint.value.method || 'GET',
    headers: newEndpoint.value.headers || {},
    timeout: newEndpoint.value.timeout || 10,
    isActive: true,
    isBpjsEndpoint,
    // Auto-enable proxy for BPJS endpoints so backend adds auth headers
    useProxy: isBpjsEndpoint
  };

  customEndpoints.value.push(endpoint);
  saveCustomEndpoints();
  
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
  fetchMonitoringData();
};

const editCustomEndpoint = (endpoint: CustomEndpoint) => {
  editingEndpoint.value = endpoint;
  newEndpoint.value = { ...endpoint };
  showAddEndpointModal.value = true;
};

const updateCustomEndpoint = () => {
  if (editingEndpoint.value) {
    const index = customEndpoints.value.findIndex(ep => ep.id === editingEndpoint.value!.id);
    if (index !== -1) {
      // Normalize then recalculate BPJS detection when URL changes
      const normalized = normalizeBpjsUrl(newEndpoint.value.url || editingEndpoint.value.url);
      if (normalized.corrected) {
        error.value = 'Memperbaiki URL: mengganti domain .go.ids menjadi .go.id';
        setTimeout(() => { error.value = null; }, 5000);
      }
      const urlStr = normalized.url;
      const isBpjsEndpoint = urlStr.includes('bpjs-kesehatan.go.id') ||
                             urlStr.includes('apijkn.bpjs-kesehatan.go.id') ||
                             urlStr.includes('new-api.bpjs-kesehatan.go.id') ||
                             urlStr.includes('bpjs-kesehatan.go.') ||
                             urlStr.includes('apijkn.bpjs-kesehatan.go.');

      customEndpoints.value[index] = {
        ...editingEndpoint.value,
        name: newEndpoint.value.name || editingEndpoint.value.name,
        url: urlStr,
        description: newEndpoint.value.description || editingEndpoint.value.description || '',
        method: newEndpoint.value.method || editingEndpoint.value.method || 'GET',
        headers: newEndpoint.value.headers || editingEndpoint.value.headers || {},
        timeout: newEndpoint.value.timeout || editingEndpoint.value.timeout || 10,
        isActive: newEndpoint.value.isActive !== undefined ? newEndpoint.value.isActive! : editingEndpoint.value.isActive,
        isBpjsEndpoint,
        // Auto-enable proxy for BPJS endpoints so backend adds auth headers
        useProxy: isBpjsEndpoint
      } as CustomEndpoint;
      saveCustomEndpoints();
    }
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
  
  editingEndpoint.value = null;
  showAddEndpointModal.value = false;
  fetchMonitoringData();
};

const deleteCustomEndpoint = (id: string) => {
  customEndpoints.value = customEndpoints.value.filter(ep => ep.id !== id);
  saveCustomEndpoints();
  fetchMonitoringData();
};

const toggleEndpointStatus = (id: string) => {
  const endpoint = customEndpoints.value.find(ep => ep.id === id);
  if (endpoint) {
    endpoint.isActive = !endpoint.isActive;
    saveCustomEndpoints();
    fetchMonitoringData();
  }
};

const cancelAddEndpoint = () => {
  newEndpoint.value = {
    name: '',
    url: '',
    description: '',
    method: 'GET',
    headers: {},
    timeout: 10,
    isActive: true
  };
  editingEndpoint.value = null;
  showAddEndpointModal.value = false;
};

// Test individual custom endpoint
const testCustomEndpoint = async (endpoint: CustomEndpoint) => {
  try {
    // Always use the test-custom-endpoint route which handles BPJS authentication automatically
    const url = '/bpjs-monitoring/test-custom-endpoint';
    
    const payload = {
      url: endpoint.url,
      method: endpoint.method || 'GET',
      timeout: endpoint.timeout || 10,
      headers: endpoint.headers || {}
    };

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();
    
    return {
      name: endpoint.name,
      url: endpoint.url,
      response_time: result.response_time || 0,
      code: result.code || 'ERROR',
      message: result.message || 'Unknown error',
      status: result.status || 'error',
      severity: result.severity || 'critical',
      description: endpoint.description,
      isCustom: true,
      customId: endpoint.id,
      httpStatus: result.http_status,
      help: result.help,
      // Flag 404 endpoints for potential removal
      is404: result.status === 'not_found' || result.code === 404 || result.code === '404',
      errorType: result.status === 'not_found' ? 'ENDPOINT_NOT_FOUND' : 
                 result.status === 'auth_error' ? 'AUTHENTICATION_ERROR' : 
                 result.status
    };
  } catch (err) {
    return {
      name: endpoint.name,
      url: endpoint.url,
      response_time: 0,
      code: 'ERROR',
      message: err instanceof Error ? err.message : 'Network error',
      status: 'error' as const,
      severity: 'critical',
      description: endpoint.description,
      isCustom: true,
      customId: endpoint.id
    };
  }
};

const fetchMonitoringData = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    const response = await fetch('/bpjs-monitoring/data');
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
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
      
      customTests.forEach((result) => {
        if (result.status === 'fulfilled') {
          customResults.push(result.value);
        }
      });
    }

    // Combine default and custom endpoints
    const combinedEndpoints = [...data.endpoints, ...customResults];
    
    // Recalculate summary with custom endpoints
    const totalEndpoints = combinedEndpoints.length;
    const successCount = combinedEndpoints.filter(ep => ep.status === 'success').length;
    const errorCount = totalEndpoints - successCount;
    const totalResponseTime = combinedEndpoints.reduce((sum, ep) => sum + ep.response_time, 0);
    const avgResponseTime = totalEndpoints > 0 ? Math.round((totalResponseTime / totalEndpoints) * 100) / 100 : 0;
    const uptimePercentage = totalEndpoints > 0 ? Math.round((successCount / totalEndpoints) * 100 * 100) / 100 : 0;

    monitoringData.value = {
      summary: {
        total: totalEndpoints,
        success: successCount,
        error: errorCount,
        avg_response_time: avgResponseTime,
        uptime_percentage: uptimePercentage,
        uptime_24h: data.summary.uptime_24h || uptimePercentage,
        avg_response_time_24h: data.summary.avg_response_time_24h || avgResponseTime
      },
      endpoints: combinedEndpoints,
      alerts: data.alerts || [],
      network_diagnostics: data.network_diagnostics,
      timestamp: data.timestamp
    };

    // Save to historical data
    saveHistoricalData(monitoringData.value);
    
    lastUpdate.value = new Date().toLocaleTimeString();
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

// Network Diagnostics Helper Functions
const getDiagnosticStatusColor = (status: string) => {
  switch (status) {
    case 'good': 
    case 'success': 
      return 'bg-green-500';
    case 'warning': 
      return 'bg-yellow-500';
    case 'critical': 
    case 'error': 
      return 'bg-red-500';
    default: 
      return 'bg-gray-500';
  }
};

const getDiagnosticIcon = (rootCause: string) => {
  switch (rootCause) {
    case 'all_systems_normal':
      return CheckCircle;
    case 'local_network_issue':
    case 'bpjs_server_issue':
    case 'mixed_connectivity_issue':
      return XCircle;
    case 'intermittent_issue':
      return AlertTriangle;
    default:
      return Wifi;
  }
};

// Chart computed properties with localStorage historical data
const responseTimeChartData = computed(() => {
  if (!monitoringData.value) return { series: [], categories: [] };
  
  const endpoints = monitoringData.value.endpoints.slice(0, 8); // Limit to 8 endpoints for readability
  const series = [{
    name: 'Response Time (ms)',
    data: endpoints.map(ep => ep.response_time)
  }];
  const categories = endpoints
    .filter(Boolean)
    .map(ep => ep.name || ep.url || 'Unknown');
  
  return { series, categories };
});

const statusDistributionData = computed(() => {
  if (!monitoringData.value) return { series: [], labels: [] };
  
  const summary = monitoringData.value.summary;
  const series = [summary.success, summary.error];
  const labels = ['Success', 'Error'];
  
  return { series, labels };
});

// Historical trends from localStorage
const historicalTrends = computed(() => {
  const data = historicalData.value;
  if (data.length < 10) return { response_time: [], uptime: [], timestamps: [] };
  
  // Get last 24 data points (12 hours at 30-second intervals)
  const recentData = data.slice(-24);
  
  return {
    response_time: recentData.map(d => d.summary.avg_response_time),
    uptime: recentData.map(d => d.summary.uptime_percentage),
    timestamps: recentData.map(d => new Date(d.timestamp).toLocaleTimeString())
  };
});

// Chart options
const chartOptions = {
  responseTime: {
    chart: {
      type: 'bar',
      toolbar: { show: false },
      background: 'transparent'
    },
    theme: {
      mode: 'dark'
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        horizontal: false,
        columnWidth: '70%'
      }
    },
    dataLabels: {
      enabled: false
    },
    colors: ['#3B82F6'],
    xaxis: {
      categories: [],
      labels: {
        style: {
          colors: '#9CA3AF',
          fontSize: '12px'
        },
        rotate: -45,
        maxHeight: 60
      }
    },
    yaxis: {
      title: {
        text: 'Response Time (ms)',
        style: {
          color: '#9CA3AF'
        }
      },
      labels: {
        style: {
          colors: '#9CA3AF'
        }
      }
    },
    grid: {
      borderColor: '#374151',
      strokeDashArray: 1
    },
    tooltip: {
      theme: 'dark',
      style: {
        fontSize: '12px',
        color: '#ffffff'
      },
      y: {
        formatter: (value: number) => `${value}ms`
      }
    }
  },
  statusDistribution: {
    chart: {
      type: 'donut',
      toolbar: { show: false },
      background: 'transparent'
    },
    theme: {
      mode: 'dark'
    },
    colors: ['#10B981', '#EF4444'],
    labels: ['Success', 'Error'],
    dataLabels: {
      enabled: true,
      style: {
        colors: ['#ffffff']
      }
    },
    legend: {
      position: 'bottom',
      labels: {
        colors: '#9CA3AF'
      }
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'Total',
              color: '#9CA3AF',
              formatter: () => monitoringData.value?.summary.total.toString() || '0'
            }
          }
        }
      }
    },
    tooltip: {
      theme: 'dark',
      style: {
        fontSize: '12px',
        color: '#ffffff'
      }
    }
  }
};

// Count 404 endpoints
const count404Endpoints = (): number => {
  if (!monitoringData.value) return 0;
  return monitoringData.value.endpoints.filter(endpoint => 
    endpoint.is404 || endpoint.status === 'not_found'
  ).length;
};

// Remove all 404 endpoints
const removeAll404Endpoints = () => {
  if (!monitoringData.value) return;
  
  const endpointsToRemove = monitoringData.value.endpoints.filter(endpoint => 
    endpoint.isCustom && (endpoint.is404 || endpoint.status === 'not_found')
  );
  
  if (endpointsToRemove.length === 0) return;
  
  if (confirm(`Remove ${endpointsToRemove.length} endpoints that return 404?`)) {
    endpointsToRemove.forEach(endpoint => {
      if (endpoint.customId) {
        deleteCustomEndpoint(endpoint.customId);
      }
    });
  }
};

onMounted(() => {
  loadCustomEndpoints();
  historicalData.value = getHistoricalData();
  fetchMonitoringData();
  
  // Auto-refresh every 30 seconds
  intervalId = setInterval(() => {
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
  <Head title="BPJS API Monitoring" />
  
<AppHeaderLayout hideHeader>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            BPJS API Monitoring
          </h2>
          <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
            Real-time monitoring dashboard untuk konektivitas API BPJS
          </p>
        </div>
        
        <div class="flex items-center space-x-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            Last updated: {{ lastUpdate }}
          </div>

          <!-- Desktop actions -->
          <div class="hidden md:flex items-center space-x-2">
            <Button @click="showAddEndpointModal = true" size="sm" class="flex items-center space-x-1">
              <Plus class="w-4 h-4" />
              <span>Add Endpoint</span>
            </Button>
            
            <Button @click="showManageEndpointsModal = true" variant="outline" size="sm" class="flex items-center space-x-1">
              <Settings class="w-4 h-4" />
              <span>Manage ({{ customEndpoints.length }})</span>
            </Button>
            
            <Button 
              @click="fetchMonitoringData" 
              :disabled="isLoading"
              size="sm"
              class="flex items-center space-x-1"
            >
              <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
              <span>Refresh</span>
            </Button>
          </div>

          <!-- Mobile dropdown actions -->
          <div class="md:hidden">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button 
                  variant="outline" 
                  size="sm"
                  class="h-12 w-12 p-0 inline-flex items-center justify-center"
                  aria-label="Actions"
                >
                  <MoreVertical class="w-5 h-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuItem @click="showAddEndpointModal = true">
                  <Plus class="w-4 h-4 mr-2" />
                  Add Endpoint
                </DropdownMenuItem>
                <DropdownMenuItem @click="showManageEndpointsModal = true">
                  <Settings class="w-4 h-4 mr-2" />
                  Manage ({{ customEndpoints.length }})
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="fetchMonitoringData" :disabled="isLoading">
                  <RefreshCw :class="{ 'animate-spin': isLoading }" class="w-4 h-4 mr-2" />
                  Refresh
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Loading State -->
        <div v-if="isLoading && !monitoringData" class="text-center py-8">
          <RefreshCw class="mx-auto h-8 w-8 animate-spin text-blue-500" />
          <p class="mt-2 text-gray-600 dark:text-gray-400">Loading monitoring data...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div class="flex items-center">
            <XCircle class="h-5 w-5 text-red-500 mr-2" />
            <h3 class="text-lg font-medium text-red-800 dark:text-red-200">Error Loading Data</h3>
          </div>
          <p class="mt-2 text-red-700 dark:text-red-300">{{ error }}</p>
          <Button @click="fetchMonitoringData" class="mt-3" size="sm">
            Try Again
          </Button>
        </div>

        <!-- Main Content -->
        <div v-if="monitoringData" class="space-y-6">
          <!-- Summary Cards: Mobile slider -->
          <div class="md:hidden">
            <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-2">
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Total Endpoints</CardTitle>
                  <Activity class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ monitoringData.summary.total }}</div>
                  <p class="text-xs text-muted-foreground">Monitored endpoints</p>
                </CardContent>
              </Card>

              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Uptime</CardTitle>
                  <CheckCircle class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold text-green-600">{{ monitoringData.summary.uptime_percentage }}%</div>
                  <p class="text-xs text-muted-foreground">Current status</p>
                </CardContent>
              </Card>

              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Avg Response Time</CardTitle>
                  <Zap class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold" :class="getResponseTimeColor(monitoringData.summary.avg_response_time)">
                    {{ monitoringData.summary.avg_response_time }}ms
                  </div>
                  <p class="text-xs text-muted-foreground">Current average</p>
                </CardContent>
              </Card>

              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Status</CardTitle>
                  <Activity class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="flex items-center space-x-2">
                    <CheckCircle class="h-5 w-5 text-green-500" />
                    <span class="text-lg font-semibold text-green-600">{{ monitoringData.summary.success }}</span>
                    <XCircle class="h-5 w-5 text-red-500 ml-2" />
                    <span class="text-lg font-semibold text-red-600">{{ monitoringData.summary.error }}</span>
                  </div>
                  <p class="text-xs text-muted-foreground">Success vs Error</p>
                </CardContent>
              </Card>
            </div>
          </div>

          <!-- Summary Cards: Desktop grid -->
          <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Endpoints</CardTitle>
                <Activity class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">{{ monitoringData.summary.total }}</div>
                <p class="text-xs text-muted-foreground">Monitored endpoints</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Uptime</CardTitle>
                <CheckCircle class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold text-green-600">{{ monitoringData.summary.uptime_percentage }}%</div>
                <p class="text-xs text-muted-foreground">Current status</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Avg Response Time</CardTitle>
                <Zap class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold" :class="getResponseTimeColor(monitoringData.summary.avg_response_time)">
                  {{ monitoringData.summary.avg_response_time }}ms
                </div>
                <p class="text-xs text-muted-foreground">Current average</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Status</CardTitle>
                <Activity class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="flex items-center space-x-2">
                  <CheckCircle class="h-5 w-5 text-green-500" />
                  <span class="text-lg font-semibold text-green-600">{{ monitoringData.summary.success }}</span>
                  <XCircle class="h-5 w-5 text-red-500 ml-2" />
                  <span class="text-lg font-semibold text-red-600">{{ monitoringData.summary.error }}</span>
                </div>
                <p class="text-xs text-muted-foreground">Success vs Error</p>
              </CardContent>
            </Card>
          </div>

          <!-- 404 Endpoints Warning -->
          <div v-if="count404Endpoints() > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <AlertTriangle class="h-5 w-5 text-red-500 mr-3" />
                <div>
                  <h3 class="text-lg font-medium text-red-800 dark:text-red-200">
                    {{ count404Endpoints() }} Endpoint(s) Returning 404
                  </h3>
                  <p class="text-red-700 dark:text-red-300">
                    Some endpoints are no longer available and should be removed from monitoring.
                  </p>
                </div>
              </div>
              <Button @click="removeAll404Endpoints" variant="outline" size="sm" class="border-red-300 text-red-600 hover:bg-red-50">
                Remove All 404s
              </Button>
            </div>
          </div>

          <!-- Charts Section -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Response Time Chart -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center space-x-2">
                  <Zap class="h-5 w-5 text-blue-500" />
                  <span>Response Time Overview</span>
                </CardTitle>
                <CardDescription>Performance comparison across endpoints</CardDescription>
              </CardHeader>
              <CardContent>
                <VueApexCharts 
                  v-if="responseTimeChartData.series[0]?.data.length > 0"
                  type="bar" 
                  :options="{
                    ...chartOptions.responseTime,
                    xaxis: { ...chartOptions.responseTime.xaxis, categories: responseTimeChartData.categories }
                  }" 
                  :series="responseTimeChartData.series"
                  height="280"
                />
                <div v-else class="flex items-center justify-center h-70 text-gray-500">
                  <div class="text-center">
                    <Activity class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">No data available</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Status Distribution Chart -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center space-x-2">
                  <Activity class="h-5 w-5 text-green-500" />
                  <span>Status Distribution</span>
                </CardTitle>
                <CardDescription>Success vs Error ratio</CardDescription>
              </CardHeader>
              <CardContent>
                <VueApexCharts 
                  v-if="statusDistributionData.series.length > 0 && statusDistributionData.series.reduce((a, b) => a + b, 0) > 0"
                  type="donut" 
                  :options="chartOptions.statusDistribution"
                  :series="statusDistributionData.series"
                  height="280"
                />
                <div v-else class="flex items-center justify-center h-70 text-gray-500">
                  <div class="text-center">
                    <CheckCircle class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">No data available</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Network Diagnostics Section -->
          <div class="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center space-x-2">
                  <Wifi class="h-5 w-5 text-blue-500" />
                  <span>Network Diagnostics</span>
                </CardTitle>
                <CardDescription>Real-time network connectivity analysis to identify root cause of issues</CardDescription>
              </CardHeader>
              <CardContent>
                <!-- Debug Info -->
                <div v-if="!monitoringData?.network_diagnostics" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                  <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    ⚠️ Network diagnostics data not available. Check console logs.
                  </p>
                </div>

                <!-- Analysis Summary -->
                <div v-if="monitoringData?.network_diagnostics" class="mb-6 p-4 rounded-lg" :class="{
                  'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800': monitoringData.network_diagnostics.analysis.root_cause === 'all_systems_normal',
                  'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800': monitoringData.network_diagnostics.analysis.root_cause.includes('warning') || monitoringData.network_diagnostics.analysis.root_cause === 'intermittent_issue',
                  'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800': monitoringData.network_diagnostics.analysis.root_cause.includes('issue') && monitoringData.network_diagnostics.analysis.root_cause !== 'all_systems_normal'
                }">
                  <div class="flex items-start space-x-3">
                    <component :is="getDiagnosticIcon(monitoringData.network_diagnostics.analysis.root_cause)" 
                      class="h-6 w-6 mt-0.5 flex-shrink-0" 
                      :class="{
                        'text-green-600 dark:text-green-400': monitoringData.network_diagnostics.analysis.root_cause === 'all_systems_normal',
                        'text-yellow-600 dark:text-yellow-400': monitoringData.network_diagnostics.analysis.root_cause.includes('warning') || monitoringData.network_diagnostics.analysis.root_cause === 'intermittent_issue',
                        'text-red-600 dark:text-red-400': monitoringData.network_diagnostics.analysis.root_cause.includes('issue') && monitoringData.network_diagnostics.analysis.root_cause !== 'all_systems_normal'
                      }" 
                    />
                    <div class="flex-1">
                      <div class="flex items-center space-x-2 mb-2">
                        <h3 class="font-semibold text-lg">{{ monitoringData.network_diagnostics.analysis.summary }}</h3>
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                          {{ monitoringData.network_diagnostics.analysis.confidence }}% confidence
                        </span>
                      </div>
                      <div class="space-y-1">
                        <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300">Recommended Actions:</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                          <li v-for="recommendation in monitoringData.network_diagnostics.analysis.recommendations" :key="recommendation">
                            {{ recommendation }}
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Diagnostics Grid -->
                <div v-if="monitoringData?.network_diagnostics" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4">
                  <!-- Local Connectivity -->
                  <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                      <div :class="getDiagnosticStatusColor(monitoringData.network_diagnostics.local_connectivity.overall_status)" 
                           class="w-3 h-3 rounded-full flex-shrink-0"></div>
                      <h4 class="font-medium text-sm">Local Connectivity</h4>
                    </div>
                    <div class="space-y-2">
                      <div v-for="test in monitoringData.network_diagnostics.local_connectivity.tests.slice(0, 3)" :key="test.host" 
                           class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-800 rounded p-2">
                        <div class="flex items-center space-x-2">
                          <div :class="getDiagnosticStatusColor(test.status)" class="w-2 h-2 rounded-full flex-shrink-0"></div>
                          <span class="font-mono">{{ test.host }}</span>
                        </div>
                        <span class="text-gray-500">{{ test.response_time }}ms</span>
                      </div>
                    </div>
                  </div>

                  <!-- DNS Resolution -->
                  <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                      <div :class="getDiagnosticStatusColor(monitoringData.network_diagnostics.dns_resolution.overall_status)" 
                           class="w-3 h-3 rounded-full flex-shrink-0"></div>
                      <h4 class="font-medium text-sm">DNS Resolution</h4>
                    </div>
                    <div class="space-y-2">
                      <div v-for="(test, idx) in (monitoringData.network_diagnostics.dns_resolution.tests || []).filter(Boolean).slice(0, 3)" :key="test.domain || idx" 
                           class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-800 rounded p-2">
                        <div class="flex items-center space-x-2">
                          <div :class="getDiagnosticStatusColor(test.status)" class="w-2 h-2 rounded-full flex-shrink-0"></div>
                          <span class="font-mono text-xs">{{ test.domain?.split('.')[0] || 'unknown' }}</span>
                        </div>
                        <span class="text-gray-500">{{ test.response_time }}ms</span>
                      </div>
                    </div>
                  </div>

                  <!-- External Connectivity -->
                  <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                      <div :class="getDiagnosticStatusColor(monitoringData.network_diagnostics.external_connectivity.overall_status)" 
                           class="w-3 h-3 rounded-full flex-shrink-0"></div>
                      <h4 class="font-medium text-sm">External APIs</h4>
                    </div>
                    <div class="space-y-2">
                      <div v-for="(test, idx) in (monitoringData.network_diagnostics.external_connectivity.tests || []).filter(Boolean)" :key="test.name || test.url || test.host || idx" 
                           class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-800 rounded p-2">
                        <div class="flex items-center space-x-2">
                          <div :class="getDiagnosticStatusColor(test.status)" class="w-2 h-2 rounded-full flex-shrink-0"></div>
                          <span>{{ test.name || test.url || test.host || 'Unknown' }}</span>
                        </div>
                        <span class="text-gray-500">{{ test.response_time }}ms</span>
                      </div>
                    </div>
                  </div>

                  <!-- BPJS Infrastructure -->
                  <div class="space-y-3">
                    <div class="flex items-center space-x-2">
                      <div :class="getDiagnosticStatusColor(monitoringData.network_diagnostics.bpjs_infrastructure.overall_status)" 
                           class="w-3 h-3 rounded-full flex-shrink-0"></div>
                      <h4 class="font-medium text-sm">BPJS Infrastructure</h4>
                    </div>
                    <div class="space-y-2">
                      <div v-for="(test, idx) in (monitoringData.network_diagnostics.bpjs_infrastructure.tests || []).filter(Boolean)" :key="test.name || idx" 
                           class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-800 rounded p-2">
                        <div class="flex items-center space-x-2">
                          <div :class="getDiagnosticStatusColor(test.status)" class="w-2 h-2 rounded-full flex-shrink-0"></div>
                          <span class="truncate">{{ test.name?.replace('BPJS ', '') || 'Unknown' }}</span>
                        </div>
                        <span class="text-gray-500">{{ test.response_time }}ms</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Timestamp -->
                <div v-if="monitoringData?.network_diagnostics" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <p class="text-xs text-gray-500">
                    Last diagnostic: {{ monitoringData.network_diagnostics.timestamp }}
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Endpoints Grid -->
          <Card>
            <CardHeader>
              <CardTitle>Endpoint Status</CardTitle>
              <CardDescription>Real-time status of all monitored endpoints</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                  v-for="(endpoint, idx) in (monitoringData.endpoints || []).filter(Boolean)" 
                  :key="endpoint.customId || endpoint.name || endpoint.url || idx"
                  class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                  <!-- Status Header -->
                  <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-2">
                      <div :class="getStatusColor(endpoint.status)" class="w-3 h-3 rounded-full"></div>
                      <component :is="getStatusIcon(endpoint.status)" class="w-4 h-4" :class="{
                        'text-green-500': endpoint.status === 'success',
                        'text-yellow-500': endpoint.status === 'timeout',
                        'text-red-500': endpoint.status === 'error'
                      }" />
                    </div>
                    
                    <!-- Custom endpoint actions -->
                    <div v-if="endpoint.isCustom" class="flex items-center space-x-1">
                      <Button 
                        @click="editCustomEndpoint(customEndpoints.find(ep => ep.id === endpoint.customId)!)"
                        variant="ghost"
                        size="sm"
                        class="h-6 w-6 p-0"
                      >
                        <Edit class="h-3 w-3" />
                      </Button>
                      <Button 
                        @click="deleteCustomEndpoint(endpoint.customId!)"
                        variant="ghost"
                        size="sm"
                        class="h-6 w-6 p-0 text-red-500 hover:text-red-700"
                      >
                        <Trash2 class="h-3 w-3" />
                      </Button>
                    </div>
                  </div>

                  <!-- Endpoint Info -->
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <h3 class="font-medium text-sm">{{ endpoint.name || endpoint.url || 'Unknown' }}</h3>
                      <span :class="getBadgeClass(endpoint.status)">
                        {{ endpoint.status }}
                      </span>
                    </div>
                    
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="endpoint.description">
                      {{ endpoint.description }}
                    </p>
                    
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-gray-500">Response Time</span>
                      <span :class="getResponseTimeColor(endpoint.response_time)" class="font-medium">
                        {{ endpoint.response_time }}ms
                      </span>
                    </div>
                    
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-gray-500">Status Code</span>
                      <span class="font-mono">{{ endpoint.code }}</span>
                    </div>
                    
                    <div v-if="endpoint.message" class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="endpoint.message">
                      {{ endpoint.message }}
                    </div>
                    
                    <!-- 404 Error Warning -->
                    <div v-if="endpoint.is404 || endpoint.status === 'not_found'" class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                      <div class="flex items-center space-x-2">
                        <AlertTriangle class="w-4 h-4 text-red-500" />
                        <span class="text-xs text-red-700 dark:text-red-400 font-medium">404 Error Detected</span>
                      </div>
                      <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                        This endpoint is no longer available. Consider removing it.
                      </p>
                      <div v-if="endpoint.isCustom" class="mt-2">
                        <Button 
                          @click="deleteCustomEndpoint(endpoint.customId!)"
                          variant="outline"
                          size="sm"
                          class="h-6 text-xs border-red-300 text-red-600 hover:bg-red-50"
                        >
                          Remove 404 Endpoint
                        </Button>
                      </div>
                    </div>
                    
                    <!-- Authentication Error Warning -->
                    <div v-else-if="endpoint.status === 'auth_error'" class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                      <div class="flex items-center space-x-2">
                        <Shield class="w-4 h-4 text-yellow-500" />
                        <span class="text-xs text-yellow-700 dark:text-yellow-400 font-medium">Auth Issue</span>
                      </div>
                      <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                        {{ endpoint.help || 'Check BPJS API credentials' }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

    <!-- Add/Edit Endpoint Modal -->
    <div v-if="showAddEndpointModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="cancelAddEndpoint">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-4">
          {{ editingEndpoint ? 'Edit Endpoint' : 'Add Custom Endpoint' }}
        </h3>
        
        <form @submit.prevent="editingEndpoint ? updateCustomEndpoint() : addCustomEndpoint()" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input 
              v-model="newEndpoint.name" 
              type="text" 
              required 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="Endpoint name"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">URL</label>
            <input 
              v-model="newEndpoint.url" 
              type="url" 
              required 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/..."
            />
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
              💡 BPJS endpoints (bpjs-kesehatan.go.id) akan otomatis menggunakan authentication headers (consid, secretkey, userkey)
            </p>
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <input 
              v-model="newEndpoint.description" 
              type="text" 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="Brief description"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Timeout (seconds)</label>
            <input 
              v-model.number="newEndpoint.timeout" 
              type="number" 
              min="1" 
              max="60" 
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>
          
          <div class="flex justify-end space-x-2 pt-4">
            <Button type="button" variant="outline" @click="cancelAddEndpoint">
              Cancel
            </Button>
            <Button type="submit">
              {{ editingEndpoint ? 'Update' : 'Add' }}
            </Button>
          </div>
        </form>
      </div>
    </div>

    <!-- Manage Endpoints Modal -->
    <div v-if="showManageEndpointsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showManageEndpointsModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Manage Custom Endpoints</h3>
          <Button variant="outline" @click="showManageEndpointsModal = false">Close</Button>
        </div>
        
        <div v-if="customEndpoints.length === 0" class="text-center py-8 text-gray-500">
          <p>No custom endpoints yet</p>
          <Button @click="showAddEndpointModal = true; showManageEndpointsModal = false" class="mt-2">
            Add First Endpoint
          </Button>
        </div>
        
        <div v-else class="space-y-3">
          <div 
            v-for="endpoint in customEndpoints" 
            :key="endpoint.id"
            class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg"
          >
            <div class="flex-1">
              <div class="flex items-center space-x-2">
                <div :class="endpoint.isActive ? 'bg-green-500' : 'bg-gray-400'" class="w-2 h-2 rounded-full"></div>
                <h4 class="font-medium">{{ endpoint.name || endpoint.url || 'Unknown' }}</h4>
                <span v-if="endpoint.isBpjsEndpoint" class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">BPJS</span>
              </div>
              <p class="text-sm text-gray-500 truncate">{{ endpoint.url }}</p>
              <p class="text-xs text-gray-400">{{ endpoint.description }}</p>
            </div>
            
            <div class="flex items-center space-x-2">
              <Button 
                @click="toggleEndpointStatus(endpoint.id)"
                variant="outline"
                size="sm"
                :class="endpoint.isActive ? 'text-yellow-600' : 'text-green-600'"
              >
                {{ endpoint.isActive ? 'Disable' : 'Enable' }}
              </Button>
              <Button 
                @click="editCustomEndpoint(endpoint); showManageEndpointsModal = false"
                variant="outline"
                size="sm"
              >
                <Edit class="h-3 w-3" />
              </Button>
              <Button 
                @click="deleteCustomEndpoint(endpoint.id)"
                variant="outline"
                size="sm"
                class="text-red-500 hover:text-red-700"
              >
                <Trash2 class="h-3 w-3" />
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  <FloatingThemeToggle />
</AppHeaderLayout>
</template>

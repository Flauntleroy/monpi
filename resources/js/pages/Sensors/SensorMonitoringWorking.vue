<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import FloatingThemeToggle from '@/components/FloatingThemeToggle.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import RealtimeLineChart from '@/components/charts/RealtimeLineChart.vue';
import { 
  Activity, 
  Thermometer, 
  Droplets,
  Wifi,
  Clock,
  RefreshCw,
  AlertTriangle,
  Database,
  Zap
} from 'lucide-vue-next';

interface SensorReading {
  id: number;
  device_id: string;
  temperature_c: number;
  humidity: number;
  recorded_at: string;
}

interface DeviceStatus {
  device_id: string;
  temperature_c: number;
  humidity: number;
  recorded_at: string;
  status: 'online' | 'warning' | 'offline';
  last_seen_minutes: number;
}

interface SummaryData {
  total_readings: number;
  devices_count: number;
  avg_temperature: number;
  avg_humidity: number;
  min_temperature: number;
  max_temperature: number;
  min_humidity: number;
  max_humidity: number;
  avg_temperature_24h: number;
  avg_humidity_24h: number;
}

interface MonitoringData {
  summary: SummaryData;
  devices: DeviceStatus[];
  recent_readings: SensorReading[];
  devices_list: string[];
  timestamp: string;
}

const breadcrumbs = [
  {
    title: 'Sensor Monitoring',
    href: '/sensor-monitoring',
  },
];

const monitoringData = ref<MonitoringData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const lastUpdate = ref<string>('');
const selectedDevice = ref<string | null>(null);
const limit = ref<number>(100);

// Realtime chart state
const chartLabels = ref<string[]>([]);
const chartDatasets = ref<any[]>([]);
const isDarkChart = ref(false);

// Chart controls
const maxPoints = ref(50);
const refreshIntervalMs = ref(30000);
const isPaused = ref(false);
const showTemperatureOnly = ref(false);

let intervalId: number | null = null;

// Computed properties
const onlineDevices = computed(() => {
  return monitoringData.value?.devices.filter(d => d.status === 'online').length || 0;
});

const warningDevices = computed(() => {
  return monitoringData.value?.devices.filter(d => d.status === 'warning').length || 0;
});

const offlineDevices = computed(() => {
  return monitoringData.value?.devices.filter(d => d.status === 'offline').length || 0;
});

const displayDatasets = computed(() => {
  if (showTemperatureOnly.value) {
    return chartDatasets.value.filter(ds => ds.id === 'temp');
  }
  return chartDatasets.value;
});

const filteredDevices = computed(() => {
  if (!selectedDevice.value) return monitoringData.value?.devices || [];
  return monitoringData.value?.devices.filter(d => d.device_id === selectedDevice.value) || [];
});

const filteredReadings = computed(() => {
  if (!selectedDevice.value) return monitoringData.value?.recent_readings || [];
  return monitoringData.value?.recent_readings.filter(r => r.device_id === selectedDevice.value) || [];
});

// Status colors
const getStatusColor = (status: string) => {
  switch (status) {
    case 'online': return 'bg-green-500';
    case 'warning': return 'bg-yellow-500';
    case 'offline': return 'bg-red-500';
    default: return 'bg-gray-500';
  }
};

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'online': return Wifi;
    case 'warning': return Clock;
    case 'offline': return AlertTriangle;
    default: return Clock;
  }
};

// Temperature color coding
const getTempColor = (temp: number) => {
  if (temp < 18) return 'text-blue-600';
  if (temp > 30) return 'text-red-600';
  return 'text-green-600';
};

const getTempBadgeClass = (temp: number) => {
  if (temp < 18) return 'inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800';
  if (temp > 30) return 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800';
  return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
};

// Humidity color coding
const getHumidityColor = (humidity: number) => {
  if (humidity < 30) return 'text-orange-600';
  if (humidity > 70) return 'text-blue-600';
  return 'text-green-600';
};

const getHumidityBadgeClass = (humidity: number) => {
  if (humidity < 30) return 'inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-800';
  if (humidity > 70) return 'inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800';
  return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
};

const updateIsDarkChart = () => {
  if (typeof document !== 'undefined') {
    isDarkChart.value = document.documentElement.classList.contains('dark');
  }
};

// Fetch monitoring data
const fetchMonitoringData = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    // Use mock API endpoint for testing
    const params = new URLSearchParams();
    if (selectedDevice.value) params.set('device_id', selectedDevice.value);
    params.set('limit', String(limit.value));
    
    const response = await fetch(`/api/sensor-monitoring/data.php?${params.toString()}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    monitoringData.value = data;
    lastUpdate.value = data.timestamp;
    
    // Update chart data
    updateChartData(data);
    
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'An error occurred';
    console.error('Error fetching monitoring data:', err);
  } finally {
    isLoading.value = false;
  }
};

const updateChartData = (data: MonitoringData) => {
  // Process recent readings for chart
  const readings = data.recent_readings;
  if (readings.length === 0) return;
  
  // Create labels (timestamps)
  const labels = readings.map(r => new Date(r.recorded_at).toLocaleTimeString());
  chartLabels.value = labels;
  
  // Create temperature dataset
  const tempData = readings.map(r => r.temperature_c);
  const humidityData = readings.map(r => r.humidity);
  
  chartDatasets.value = [
    {
      id: 'temp',
      label: 'Suhu (°C)',
      data: tempData,
      borderColor: '#ef4444',
      backgroundColor: 'rgba(239, 68, 68, 0.1)',
      pointRadius: 2,
      tension: 0.25,
    },
    {
      id: 'humid',
      label: 'Kelembaban (%)',
      data: humidityData,
      borderColor: '#06b6d4',
      backgroundColor: 'rgba(6, 182, 212, 0.1)',
      pointRadius: 2,
      tension: 0.25,
    }
  ];
  
  // Limit data points
  if (chartLabels.value.length > maxPoints.value) {
    chartLabels.value = chartLabels.value.slice(-maxPoints.value);
  }
};

const formatTime = (timeString: string) => {
  return new Date(timeString).toLocaleTimeString();
};

const formatLastSeen = (minutes: number) => {
  if (minutes < 1) return 'Baru saja';
  if (minutes < 60) return `${minutes} menit lalu`;
  return `${Math.floor(minutes / 60)} jam lalu`;
};

// Watchers
watch(refreshIntervalMs, () => {
  if (!isPaused.value) {
    if (intervalId) clearInterval(intervalId);
    intervalId = window.setInterval(fetchMonitoringData, refreshIntervalMs.value);
  }
});

watch(isPaused, (paused) => {
  if (paused) {
    if (intervalId) { clearInterval(intervalId); intervalId = null; }
  } else {
    intervalId = window.setInterval(fetchMonitoringData, refreshIntervalMs.value);
  }
});

watch(maxPoints, (newMax) => {
  const n = Math.max(10, Math.min(200, Number(newMax) || 50));
  if (chartLabels.value.length > n) {
    chartLabels.value = chartLabels.value.slice(-n);
    chartDatasets.value.forEach(ds => {
      if (ds.data.length > n) ds.data = ds.data.slice(-n);
    });
  }
});

onMounted(async () => {
  await fetchMonitoringData();
  
  // Start auto refresh
  intervalId = window.setInterval(fetchMonitoringData, refreshIntervalMs.value);
  
  // Setup dark mode detection
  updateIsDarkChart();
  const mql = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const handler = () => updateIsDarkChart();
  if (mql) mql.addEventListener('change', handler);
  
  // Store for cleanup
  (isDarkChart as any)._mql = mql;
  (isDarkChart as any)._handler = handler;
});

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
  const mql = (isDarkChart as any)._mql as MediaQueryList | null;
  const handler = (isDarkChart as any)._handler as (() => void) | null;
  if (mql && handler) mql.removeEventListener('change', handler as EventListener);
});
</script>

<template>
  <Head title="Sensor Monitoring Dashboard" />
  <AppHeaderLayout :breadcrumbs="breadcrumbs" fluid hideHeader>
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            Sensor Monitoring
          </h1>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ monitoringData?.devices.length || 0 }} devices • {{ onlineDevices }} online
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Last updated: {{ lastUpdate }}
          </p>
        </div>
        <div class="flex items-center gap-4">
          <!-- Desktop actions -->
          <div class="hidden md:flex items-center gap-4">
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

          <!-- Mobile dropdown actions -->
          <div class="md:hidden">
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
      </div>

      <!-- Error State -->
      <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center">
          <AlertTriangle class="w-5 h-5 text-red-500 mr-2" />
          <span class="text-red-700 dark:text-red-300">{{ error }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading && !monitoringData" class="flex items-center justify-center h-64">
        <div class="text-center">
          <RefreshCw class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-500" />
          <p class="text-gray-600 dark:text-gray-400">Loading sensor data...</p>
        </div>
      </div>

      <!-- Main Content -->
      <div v-if="monitoringData" class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6">
        <!-- Sidebar: Device List -->
        <Card class="hidden md:block lg:sticky lg:top-6 self-start">
          <CardHeader>
            <CardTitle>Devices</CardTitle>
            <CardDescription>Daftar perangkat sensor</CardDescription>
          </CardHeader>
          <CardContent>
            <!-- Device Filter -->
            <div class="mb-4">
              <select 
                v-model="selectedDevice" 
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              >
                <option :value="null">Semua Perangkat</option>
                <option v-for="device in monitoringData.devices_list" :key="device" :value="device">
                  {{ device }}
                </option>
              </select>
            </div>
            
            <div class="space-y-2">
              <div
                v-for="device in filteredDevices"
                :key="device.device_id"
                @click="selectedDevice = device.device_id"
                :class="[
                  'p-3 border rounded-lg cursor-pointer transition-colors',
                  selectedDevice === device.device_id
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'
                ]"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <div :class="getStatusColor(device.status)" class="w-2.5 h-2.5 rounded-full"></div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ device.device_id }}
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatLastSeen(device.last_seen_minutes) }}
                  </div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                  <div class="text-xs">
                    <span :class="getTempBadgeClass(device.temperature_c)">
                      {{ device.temperature_c.toFixed(1) }}°C
                    </span>
                  </div>
                  <div class="text-xs">
                    <span :class="getHumidityBadgeClass(device.humidity)">
                      {{ device.humidity.toFixed(1) }}%
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Main Area: Summary, Chart, and Details -->
        <div class="flex flex-col space-y-6">
          <!-- Summary Cards: Mobile -->
          <div class="md:hidden order-1">
            <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-2">
              <!-- Total Devices -->
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Total Devices</CardTitle>
                  <Database class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ monitoringData.summary.devices_count }}</div>
                  <p class="text-xs text-muted-foreground mt-1">{{ onlineDevices }} online</p>
                </CardContent>
              </Card>

              <!-- Online Devices -->
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Online Devices</CardTitle>
                  <Wifi class="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold text-green-600">{{ onlineDevices }}</div>
                  <div class="text-xs text-muted-foreground mt-1">
                    {{ warningDevices }} warning • {{ offlineDevices }} offline
                  </div>
                </CardContent>
              </Card>

              <!-- Average Temperature -->
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Avg Temperature</CardTitle>
                  <Thermometer class="h-4 w-4 text-red-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold" :class="getTempColor(monitoringData.summary.avg_temperature)">
                    {{ monitoringData.summary.avg_temperature.toFixed(1) }}°C
                  </div>
                  <div class="text-xs text-muted-foreground mt-1">
                    Range: {{ monitoringData.summary.min_temperature.toFixed(1) }} - {{ monitoringData.summary.max_temperature.toFixed(1) }}°C
                  </div>
                </CardContent>
              </Card>

              <!-- Average Humidity -->
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Avg Humidity</CardTitle>
                  <Droplets class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold" :class="getHumidityColor(monitoringData.summary.avg_humidity)">
                    {{ monitoringData.summary.avg_humidity.toFixed(1) }}%
                  </div>
                  <div class="text-xs text-muted-foreground mt-1">
                    Range: {{ monitoringData.summary.min_humidity.toFixed(1) }} - {{ monitoringData.summary.max_humidity.toFixed(1) }}%
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          <!-- Device Filter Mobile -->
          <Card class="md:hidden order-2">
            <CardHeader>
              <CardTitle>Filter Perangkat</CardTitle>
              <CardDescription>Pilih perangkat untuk ditampilkan</CardDescription>
            </CardHeader>
            <CardContent>
              <select 
                v-model="selectedDevice" 
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              >
                <option :value="null">Semua Perangkat</option>
                <option v-for="device in monitoringData.devices_list" :key="device" :value="device">
                  {{ device }}
                </option>
              </select>
              
              <div class="mt-4 space-y-2">
                <div
                  v-for="device in filteredDevices.slice(0, 5)"
                  :key="device.device_id"
                  class="p-3 border rounded-lg border-gray-200 dark:border-gray-700"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <div :class="getStatusColor(device.status)" class="w-2.5 h-2.5 rounded-full"></div>
                      <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ device.device_id }}
                      </div>
                    </div>
                    <div class="text-xs">
                      <span :class="getTempBadgeClass(device.temperature_c)">
                        {{ device.temperature_c.toFixed(1) }}°C
                      </span>
                    </div>
                  </div>
                  <div class="mt-2 flex items-center justify-between">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatLastSeen(device.last_seen_minutes) }}
                    </div>
                    <div class="text-xs">
                      <span :class="getHumidityBadgeClass(device.humidity)">
                        {{ device.humidity.toFixed(1) }}%
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Summary Cards: Desktop -->
          <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Devices -->
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Devices</CardTitle>
                <Database class="h-4 w-4 text-blue-500" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">{{ monitoringData.summary.devices_count }}</div>
                <p class="text-xs text-muted-foreground mt-1">{{ onlineDevices }} online</p>
              </CardContent>
            </Card>

            <!-- Online Devices -->
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Online Devices</CardTitle>
                <Wifi class="h-4 w-4 text-green-500" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold text-green-600">{{ onlineDevices }}</div>
                <div class="text-xs text-muted-foreground mt-1">
                  {{ warningDevices }} warning • {{ offlineDevices }} offline
                </div>
              </CardContent>
            </Card>

            <!-- Average Temperature -->
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Avg Temperature</CardTitle>
                <Thermometer class="h-4 w-4 text-red-500" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold" :class="getTempColor(monitoringData.summary.avg_temperature)">
                  {{ monitoringData.summary.avg_temperature.toFixed(1) }}°C
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                  Range: {{ monitoringData.summary.min_temperature.toFixed(1) }} - {{ monitoringData.summary.max_temperature.toFixed(1) }}°C
                </div>
              </CardContent>
            </Card>

            <!-- Average Humidity -->
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Avg Humidity</CardTitle>
                <Droplets class="h-4 w-4 text-blue-500" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold" :class="getHumidityColor(monitoringData.summary.avg_humidity)">
                  {{ monitoringData.summary.avg_humidity.toFixed(1) }}%
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                  Range: {{ monitoringData.summary.min_humidity.toFixed(1) }} - {{ monitoringData.summary.max_humidity.toFixed(1) }}%
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Real-time Line Chart -->
          <Card class="order-4 md:order-none">
            <CardHeader>
              <CardTitle>Sensor Readings (Real-time)</CardTitle>
              <CardDescription>
                Suhu dan kelembaban dalam waktu nyata
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div class="flex flex-wrap items-center gap-3 mb-3">
                <label class="text-sm text-gray-600 dark:text-gray-300">Interval</label>
                <select v-model.number="refreshIntervalMs" class="px-2 py-1 border rounded text-sm bg-white dark:bg-gray-700">
                  <option :value="10000">10s</option>
                  <option :value="20000">20s</option>
                  <option :value="30000">30s</option>
                  <option :value="60000">60s</option>
                </select>

                <Button @click="isPaused = !isPaused" size="sm" variant="outline">
                  {{ isPaused ? 'Resume' : 'Pause' }}
                </Button>

                <label class="text-sm text-gray-600 dark:text-gray-300">Max Points</label>
                <input type="number" v-model.number="maxPoints" min="10" max="200" class="w-20 px-2 py-1 border rounded text-sm bg-white dark:bg-gray-700" />

                <label class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                  <input type="checkbox" v-model="showTemperatureOnly" />
                  Suhu saja
                </label>
              </div>
              <RealtimeLineChart :labels="chartLabels" :datasets="displayDatasets" :dark="isDarkChart" />
            </CardContent>
          </Card>

          <!-- Recent Readings Table -->
          <Card class="order-3 md:order-none">
            <CardHeader>
              <CardTitle>Data Terbaru</CardTitle>
              <CardDescription>
                {{ filteredReadings.length }} data terakhir 
                <span v-if="selectedDevice">dari {{ selectedDevice }}</span>
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="text-left border-b">
                      <th class="px-2 py-2">Waktu</th>
                      <th class="px-2 py-2">Device</th>
                      <th class="px-2 py-2">Suhu (°C)</th>
                      <th class="px-2 py-2">Kelembaban (%)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="reading in filteredReadings.slice(-20).reverse()" :key="`${reading.device_id}-${reading.recorded_at}`" class="border-b">
                      <td class="px-2 py-2">{{ new Date(reading.recorded_at).toLocaleString() }}</td>
                      <td class="px-2 py-2">{{ reading.device_id }}</td>
                      <td class="px-2 py-2">
                        <span :class="getTempColor(reading.temperature_c)">
                          {{ reading.temperature_c.toFixed(1) }}
                        </span>
                      </td>
                      <td class="px-2 py-2">
                        <span :class="getHumidityColor(reading.humidity)">
                          {{ reading.humidity.toFixed(1) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div v-if="filteredReadings.length === 0" class="text-center py-8 text-gray-500">
                Tidak ada data tersedia
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
    <FloatingThemeToggle />
  </AppHeaderLayout>
</template>
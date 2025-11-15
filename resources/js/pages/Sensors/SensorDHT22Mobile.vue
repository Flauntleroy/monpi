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
  RefreshCw,
  AlertTriangle
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
  { title: 'DHT22 Mobile', href: '/sensor' },
];

const monitoringData = ref<MonitoringData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const lastUpdate = ref<string>('');
const selectedDevice = ref<string | null>(null);
const limit = ref<number>(100);

const chartLabels = ref<string[]>([]);
const chartDatasets = ref<any[]>([]);
const isDarkChart = ref(false);
const maxPoints = ref(50);
const refreshIntervalMs = ref(5000);
const isPaused = ref(false);
const showTemperatureOnly = ref(false);
let intervalId: number | null = null;

const onlineDevices = computed(() => monitoringData.value?.devices.filter(d => d.status === 'online').length || 0);
const warningDevices = computed(() => monitoringData.value?.devices.filter(d => d.status === 'warning').length || 0);
const offlineDevices = computed(() => monitoringData.value?.devices.filter(d => d.status === 'offline').length || 0);
const displayDatasets = computed(() => showTemperatureOnly.value ? chartDatasets.value.filter(ds => ds.id === 'temp') : chartDatasets.value);
const filteredDevices = computed(() => !selectedDevice.value ? (monitoringData.value?.devices || []) : (monitoringData.value?.devices.filter(d => d.device_id === selectedDevice.value) || []));
const filteredReadings = computed(() => !selectedDevice.value ? (monitoringData.value?.recent_readings || []) : (monitoringData.value?.recent_readings.filter(r => r.device_id === selectedDevice.value) || []));
const latestReading = computed(() => monitoringData.value?.recent_readings?.[0] || null);
const currentDeviceStatus = computed(() => filteredDevices.value[0] || monitoringData.value?.devices?.[0] || null);

const getStatusColor = (status: string) => {
  if (status === 'online') return 'bg-green-500';
  if (status === 'warning') return 'bg-yellow-500';
  if (status === 'offline') return 'bg-red-500';
  return 'bg-gray-500';
};

const getTempColor = (temp: number) => {
  if (temp < 18) return 'text-blue-600';
  if (temp > 30) return 'text-red-600';
  return 'text-green-600';
};

const getHumidityColor = (humidity: number) => {
  if (humidity < 30) return 'text-orange-600';
  if (humidity > 70) return 'text-blue-600';
  return 'text-green-600';
};

const deviceDisplayName = (id: string) => (id ? 'Servo DHT22' : 'Servo DHT22');

const updateIsDarkChart = () => {
  if (typeof document !== 'undefined') {
    isDarkChart.value = document.documentElement.classList.contains('dark');
  }
};

const fetchMonitoringData = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    const params = new URLSearchParams();
    if (selectedDevice.value) params.set('device_id', selectedDevice.value);
    params.set('limit', String(limit.value));
    const response = await fetch(`/sensor-monitoring/data?${params.toString()}`);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();
    monitoringData.value = data;
    lastUpdate.value = data.timestamp;
    updateChartData(data);
  } catch (err: any) {
    try {
      const fallback = await fetch('/api/sensor-monitoring/data.php');
      const data = await fallback.json();
      monitoringData.value = data;
      lastUpdate.value = data.timestamp;
      updateChartData(data);
      error.value = null;
    } catch (e: any) {
      error.value = err?.message || 'An error occurred';
    }
  } finally {
    isLoading.value = false;
  }
};

const updateChartData = (data: MonitoringData) => {
  const readings = data.recent_readings;
  if (!readings.length) return;
  const labels = readings.map(r => new Date(r.recorded_at).toLocaleTimeString());
  chartLabels.value = labels;
  const tempData = readings.map(r => r.temperature_c);
  const humidityData = readings.map(r => r.humidity);
  chartDatasets.value = [
    { id: 'temp', label: 'Suhu (°C)', data: tempData, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', pointRadius: 2, tension: 0.25 },
    { id: 'humid', label: 'Kelembaban (%)', data: humidityData, borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,0.1)', pointRadius: 2, tension: 0.25 }
  ];
  if (chartLabels.value.length > maxPoints.value) {
    chartLabels.value = chartLabels.value.slice(-maxPoints.value);
  }
};

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
    chartDatasets.value.forEach(ds => { if (ds.data.length > n) ds.data = ds.data.slice(-n); });
  }
});

onMounted(async () => {
  await fetchMonitoringData();
  intervalId = window.setInterval(fetchMonitoringData, refreshIntervalMs.value);
  updateIsDarkChart();
  const mql = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const handler = () => updateIsDarkChart();
  if (mql) mql.addEventListener('change', handler);
  (isDarkChart as any)._mql = mql;
  (isDarkChart as any)._handler = handler;
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
  const mql = (isDarkChart as any)._mql as MediaQueryList | null;
  const handler = (isDarkChart as any)._handler as (() => void) | null;
  if (mql && handler) mql.removeEventListener('change', handler as EventListener);
});
</script>

<template>
  <Head title="DHT22 Mobile Monitoring" />
  <AppHeaderLayout :breadcrumbs="breadcrumbs" fluid hideHeader>
    <div class="flex h-full flex-1 flex-col gap-5 p-4">
      <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
        <div class="flex items-center">
          <AlertTriangle class="w-5 h-5 text-red-500 mr-2" />
          <span class="text-red-700 dark:text-red-300">{{ error }}</span>
        </div>
      </div>

      <div v-if="isLoading && !monitoringData" class="flex items-center justify-center h-48">
        <div class="text-center">
          <RefreshCw class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-500" />
          <p class="text-gray-600 dark:text-gray-400">Memuat data sensor...</p>
        </div>
      </div>

      <div v-if="monitoringData" class="space-y-5">
        <Card class="overflow-hidden rounded-2xl text-white bg-gradient-to-r from-[#141E30] to-[#35577D] border border-white/10 shadow-xl">
          <CardContent class="p-0">
            <div class="relative">
              <div class="absolute inset-0 pointer-events-none">
                <div class="absolute inset-x-4 top-0 h-10 bg-white/10 blur-md rounded-full opacity-20"></div>
                <div class="absolute inset-x-6 bottom-2 h-8 bg-black/30 blur-lg rounded-full opacity-25"></div>
              </div>
              <div class="p-5 relative">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div :class="getStatusColor(currentDeviceStatus?.status || 'offline')" class="w-2.5 h-2.5 rounded-full"></div>
                    <div class="text-sm font-semibold text-white">{{ deviceDisplayName(currentDeviceStatus?.device_id || '') }}</div>
                  </div>
                  <Button @click="fetchMonitoringData" :disabled="isLoading" variant="outline" size="sm" class="bg-white/10 border-white/20 text-white hover:bg-white/20">
                    <RefreshCw :class="{ 'animate-spin': isLoading }" class="w-4 h-4 mr-2" />
                    Refresh
                  </Button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-4">
                  <div class="rounded-2xl p-4 ring-1 ring-white/20 text-white bg-white/10 backdrop-blur-md shadow-md hover:bg-white/15 transition-colors">
                    <div class="flex items-center justify-between">
                      <div class="text-xs">Suhu</div>
                      <Thermometer class="w-4 h-4" />
                    </div>
                    <div class="mt-2 text-3xl font-bold" :class="latestReading ? '' : 'text-white/70'">
                      {{ latestReading ? latestReading.temperature_c.toFixed(1) : '--' }}°C
                    </div>
                  </div>
                  <div class="rounded-2xl p-4 ring-1 ring-white/20 text-white bg-white/10 backdrop-blur-md shadow-md hover:bg-white/15 transition-colors">
                    <div class="flex items-center justify-between">
                      <div class="text-xs">Kelembaban</div>
                      <Droplets class="w-4 h-4" />
                    </div>
                    <div class="mt-2 text-3xl font-bold" :class="latestReading ? '' : 'text-white/70'">
                      {{ latestReading ? latestReading.humidity.toFixed(1) : '--' }}%
                    </div>
                  </div>
                </div>
                <div class="mt-3 text-xs text-white/80">Terakhir diperbarui: {{ lastUpdate }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <div class="flex overflow-x-auto snap-x snap-mandatory gap-3 pb-1">
          <Card class="min-w-[80%] snap-start rounded-2xl text-white bg-gradient-to-r from-[#141E30] to-[#35577D] ring-1 ring-white/10 shadow-md">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Avg Temperature</CardTitle>
              <Thermometer class="h-4 w-4 text-red-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ monitoringData.summary.avg_temperature.toFixed(1) }}°C</div>
              <div class="text-xs text-white/70 mt-1">Range: {{ monitoringData.summary.min_temperature.toFixed(1) }} - {{ monitoringData.summary.max_temperature.toFixed(1) }}°C</div>
            </CardContent>
          </Card>

          <Card class="min-w-[80%] snap-start rounded-2xl text-white bg-gradient-to-r from-[#35577D] to-[#141E30] ring-1 ring-white/10 shadow-md">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Avg Humidity</CardTitle>
              <Droplets class="h-4 w-4 text-blue-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ monitoringData.summary.avg_humidity.toFixed(1) }}%</div>
              <div class="text-xs text-white/70 mt-1">Range: {{ monitoringData.summary.min_humidity.toFixed(1) }} - {{ monitoringData.summary.max_humidity.toFixed(1) }}%</div>
            </CardContent>
          </Card>

          <Card class="min-w-[80%] snap-start rounded-2xl text-white bg-gradient-to-r from-[#141E30] to-[#35577D] ring-1 ring-white/10 shadow-md">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Readings</CardTitle>
              <Activity class="h-4 w-4 text-teal-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ monitoringData.summary.total_readings }}</div>
              <p class="text-xs text-white/70 mt-1">Data terakhir: {{ limit }}</p>
            </CardContent>
          </Card>

          <Card class="min-w-[80%] snap-start rounded-2xl text-white bg-gradient-to-r from-[#35577D] to-[#141E30] ring-1 ring-white/10 shadow-md">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Online Devices</CardTitle>
              <Wifi class="h-4 w-4 text-green-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ onlineDevices }}</div>
              <div class="text-xs text-white/70 mt-1">{{ warningDevices }} warning • {{ offlineDevices }} offline</div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Sensor Readings</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex flex-wrap items-center gap-3 mb-3">
              <label class="text-sm text-gray-600 dark:text-gray-300">Interval</label>
              <select v-model.number="refreshIntervalMs" class="px-2 py-1 border rounded text-sm">
                <option :value="5000">5s</option>
                <option :value="10000">10s</option>
                <option :value="20000">20s</option>
                <option :value="30000">30s</option>
                <option :value="60000">60s</option>
              </select>
              <Button @click="isPaused = !isPaused" size="sm" variant="outline">{{ isPaused ? 'Resume' : 'Pause' }}</Button>
              <label class="text-sm text-gray-600 dark:text-gray-300">Max Points</label>
              <input type="number" v-model.number="maxPoints" min="10" max="200" class="w-20 px-2 py-1 border rounded text-sm" />
              <label class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1"><input type="checkbox" v-model="showTemperatureOnly" />Suhu saja</label>
            </div>
            <RealtimeLineChart :labels="chartLabels" :datasets="displayDatasets" :dark="isDarkChart" />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Data Terbaru</CardTitle>
            <CardDescription>{{ filteredReadings.length }} data terakhir <span v-if="selectedDevice">dari {{ deviceDisplayName(selectedDevice) }}</span></CardDescription>
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
                  <tr v-for="reading in filteredReadings.slice(0, 100)" :key="`${reading.device_id}-${reading.recorded_at}`" class="border-b">
                    <td class="px-2 py-2">{{ new Date(reading.recorded_at).toLocaleString() }}</td>
                    <td class="px-2 py-2">{{ deviceDisplayName(reading.device_id) }}</td>
                    <td class="px-2 py-2"><span :class="getTempColor(reading.temperature_c)">{{ reading.temperature_c.toFixed(1) }}</span></td>
                    <td class="px-2 py-2"><span :class="getHumidityColor(reading.humidity)">{{ reading.humidity.toFixed(1) }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="filteredReadings.length === 0" class="text-center py-8 text-gray-500">Tidak ada data tersedia</div>
          </CardContent>
        </Card>
      </div>

      <FloatingThemeToggle />
    </div>
  </AppHeaderLayout>
</template>
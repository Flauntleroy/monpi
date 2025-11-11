<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import FloatingThemeToggle from '@/components/FloatingThemeToggle.vue';
import { Card, CardContent } from '@/components/ui/card';
import { updateTheme } from '@/composables/useAppearance';

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

interface EndpointHistoryEntry {
  status: 'success' | 'error' | 'timeout';
  code: string | number;
  message: string;
  response_time: number;
  timestamp: string;
}

interface MonitoringData {
  summary: {
    total: number;
    success: number;
    error: number;
    avg_response_time: number;
    uptime_percentage: number;
  };
  endpoints: EndpointData[];
  timestamp: string;
}

const monitoringData = ref<MonitoringData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const lastUpdate = ref<string>('');

// History per endpoint untuk heartbeat
const endpointHistories = ref<Record<string, EndpointHistoryEntry[]>>({});
const getEndpointKey = (ep: EndpointData, idx?: number) => ep.customId || ep.name || ep.url || String(idx ?? '0');

// Helper: warna dot status
const getStatusColor = (status: string) => {
  switch (status) {
    case 'success': return 'bg-green-500';
    case 'timeout': return 'bg-red-500'; // RTO merah
    case 'error': return 'bg-red-500';   // tidak connect merah
    default: return 'bg-gray-500';
  }
};

// Badge ms: success hijau/kuning saja; timeout/error merah
const getResponseBadgeClass = (responseTime: number) => {
  const rt = Math.round(responseTime);
  if (rt < 1000) {
    return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
  }
  return 'inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800';
};

const getBadgeClass = (status: string) => {
  switch (status) {
    case 'success':
      return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
    case 'timeout':
    case 'error':
      return 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800';
    default:
      return 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800';
  }
};

// Warna heartbeat dot: success lambat (>=1000ms) kuning, merah hanya untuk timeout/error
const getHeartbeatDotClass = (h: EndpointHistoryEntry) => {
  const rt = Math.round(h.response_time);
  if (h.status === 'success') {
    if (rt >= 1000) return 'bg-yellow-500';
    return 'bg-green-500';
  }
  if (h.status === 'timeout' || h.status === 'error') return 'bg-red-500';
  return 'bg-gray-500';
};

// Filter endpoint aman untuk render
const safeEndpoints = computed(() => {
  const eps = monitoringData.value?.endpoints || [];
  return eps.filter((ep: any) => ep && typeof ep === 'object' && (typeof ep.name === 'string' || typeof ep.url === 'string'));
});

// Polling data
let intervalId: number | null = null;
const fetchMonitoringData = async () => {
  try {
    isLoading.value = true;
    error.value = null;

    const response = await fetch('/bpjs-monitoring/data');
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      throw new Error(`Expected JSON, got: ${contentType}. Response: ${text.substring(0, 200)}...`);
    }

    const data = await response.json();
    monitoringData.value = data;
    lastUpdate.value = data.timestamp;

    // Update history untuk heartbeat (maks 30 entri)
    const ts = data.timestamp;
    const allEndpoints: EndpointData[] = (Array.isArray(data.endpoints) ? data.endpoints : []) as EndpointData[];
    allEndpoints.forEach((ep, idx) => {
      const key = getEndpointKey(ep, idx);
      if (!endpointHistories.value[key]) endpointHistories.value[key] = [];
      endpointHistories.value[key].unshift({
        status: ep.status,
        code: ep.code,
        message: ep.message,
        response_time: Math.round(ep.response_time),
        timestamp: ts,
      });
      if (endpointHistories.value[key].length > 30) endpointHistories.value[key].pop();
    });
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'An error occurred';
    console.error('Error fetching monitoring data:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  // Force default theme to light on Mini Monitoring
  try {
    updateTheme('light');
  } catch {}

  fetchMonitoringData();
  intervalId = window.setInterval(fetchMonitoringData, 30000);
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
  // Restore theme preference when leaving the page
  try {
    const saved = (typeof window !== 'undefined' ? localStorage.getItem('appearance') : null) as 'light' | 'dark' | 'system' | null;
    updateTheme(saved || 'system');
  } catch {}
});

const breadcrumbs = [
  { title: 'Mini Monitoring', href: '/mini-monitoring' },
];
</script>

<template>
  <Head title="MonPi" />
  <AppHeaderLayout :breadcrumbs="breadcrumbs" hideHeader>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Monitoring BPJS</h1>
        <span class="text-xs text-gray-500 dark:text-gray-400">Last update: {{ lastUpdate || '-' }}</span>
      </div>

      <Card>
        <CardContent>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div
              v-for="(endpoint, idx) in safeEndpoints"
              :key="getEndpointKey(endpoint, idx)"
              class="p-3 border rounded-lg"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div :class="getStatusColor(endpoint.status)" class="w-2.5 h-2.5 rounded-full"></div>
                  <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[220px] lg:max-w-[260px]">
                    {{ endpoint.name || endpoint.url || 'Unknown' }}
                  </div>
                </div>
                <div class="text-xs font-mono"
                     :class="endpoint.status === 'success' ? getResponseBadgeClass(endpoint.response_time) : getBadgeClass(endpoint.status)">
                  {{ endpoint.status === 'success' ? (Math.round(endpoint.response_time) + ' ms') : endpoint.code }}
                </div>
              </div>
              <div class="mt-2 flex items-center gap-1 flex-nowrap overflow-hidden">
                <span
                  v-for="(h, i) in (endpointHistories[getEndpointKey(endpoint, idx)] || []).slice(0, 30).reverse()"
                  :key="i"
                  :title="h.timestamp + ' • ' + h.status + ' • ' + h.response_time + ' ms'"
                  :class="['h-1.5 w-2 rounded-sm', getHeartbeatDotClass(h)]"
                ></span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <FloatingThemeToggle />
    </div>
  </AppHeaderLayout>
</template>
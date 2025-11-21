<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import FloatingThemeToggle from '@/components/FloatingThemeToggle.vue';
// ThemeToggle dihapus dari header mobile; gunakan FloatingThemeToggle sebagai satu-satunya kontrol
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import RealtimeLineChart from '@/components/charts/RealtimeLineChart.vue';
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
  ChevronDown,
  ChevronUp,
  MoreVertical,
  ExternalLink
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
  isBpjsEndpoint?: boolean; // Flag untuk BPJS endpoints
  useProxy?: boolean; 
}


const IGNORED_ENDPOINT_NAMES = new Set<string>([
  'Dokter',
  'Faskes',
  'Rujukan by NoRujukan'
]);

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


const chartLabels = ref<string[]>([]);
const chartValues = ref<number[]>([]);

interface LineDataset {
  id: string;
  label: string;
  data: Array<number | null>;
  borderColor?: string;
  backgroundColor?: string;
  pointRadius?: number;
  tension?: number;
}
const chartDatasets = ref<LineDataset[]>([]);
const seriesColors = ['#2563eb', '#16a34a', '#ef4444', '#f59e0b', '#9333ea', '#06b6d4', '#10b981', '#8b5cf6', '#3b82f6', '#14b8a6', '#f97316', '#dc2626'];
const getColorForId = (id: string) => {
  let hash = 0;
  for (let i = 0; i < id.length; i++) {
    hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
  }
  return seriesColors[hash % seriesColors.length];
};


const maxPoints = ref(50);
const refreshIntervalMs = ref(30000);
const isPaused = ref(false);
const showTopNSlowest = ref(false);
const topN = ref(5);
const activeEndpointIds = ref<string[]>([]);

const availableEndpoints = computed(() => chartDatasets.value.map((ds) => ({ id: ds.id, label: ds.label })));

const displayDatasets = computed(() => {
  let base = chartDatasets.value;

  if (showTopNSlowest.value) {
    const pairs = base
      .map((ds) => {
        let lastVal: number | null = null;
        for (let i = ds.data.length - 1; i >= 0; i--) {
          const v = ds.data[i];
          if (typeof v === 'number' && Number.isFinite(v)) { lastVal = v; break; }
        }
        return { ds, val: lastVal };
      })
      .filter((p) => p.val !== null) as Array<{ ds: LineDataset; val: number }>;
    pairs.sort((a, b) => b.val - a.val);
    base = pairs.slice(0, Math.max(1, topN.value)).map((p) => p.ds);
  } else if (activeEndpointIds.value.length > 0) {
    const set = new Set(activeEndpointIds.value);
    base = base.filter((ds) => set.has(ds.id));
  }

  const dynamicRadius = base.length > 10 ? 0 : 2;
  return base.map((ds) => ({ ...ds, pointRadius: dynamicRadius }));
});

const isDarkChart = ref(false);
const updateIsDarkChart = () => {
  if (typeof document !== 'undefined') {
    isDarkChart.value = document.documentElement.classList.contains('dark');
  }
};


const refreshCopy = computed(() => {
  const sec = Math.max(1, Math.round((refreshIntervalMs.value || 30000) / 1000));
  return `Refreshing every ${sec}s${isPaused.value ? ' • Paused' : ''}`;
});
const proxyEnabledCount = computed(() => customEndpoints.value.filter((e) => e?.useProxy).length);
const headerStatusCopy = computed(() => {
  const uptime = monitoringData.value?.summary?.uptime_percentage;
  const uptimeText = typeof uptime === 'number' ? `${Math.round(uptime)}%` : '—';
  const proxyText = proxyEnabledCount.value > 0 ? `Proxy: ${proxyEnabledCount.value} enabled` : 'Proxy: off';
  return `${proxyText} • Uptime: ${uptimeText}`;
});


const safeEndpoints = computed(() => {
  const eps = monitoringData.value?.endpoints || [];
  return eps.filter((ep: any) => {
    if (!ep) return false;
    const t = typeof ep;
    if (t !== 'object') return false;
    
    return typeof ep.name === 'string' || typeof ep.url === 'string';
  });
});


const saveCustomEndpoints = () => {
  localStorage.setItem('bpjs_custom_endpoints', JSON.stringify(customEndpoints.value));
};

const loadCustomEndpoints = () => {
  const stored = localStorage.getItem('bpjs_custom_endpoints');
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      
      customEndpoints.value = Array.isArray(parsed)
        ? parsed.filter((ep: any) => ep && typeof ep === 'object')
        : [];
    } catch (e) {
      console.error('Error loading custom endpoints:', e);
      customEndpoints.value = [];
    }
  }
};


const getCsrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content;
const apiFetch = async (url: string, options: any = {}) => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };
  const token = getCsrfToken();
  if (token) headers['X-CSRF-TOKEN'] = token;
  const resp = await fetch(url, { ...options, headers, credentials: 'include' });
  if (!resp.ok) {
    const text = await resp.text();
    throw new Error(`HTTP ${resp.status}: ${text.slice(0, 300)}`);
  }
  const ct = resp.headers.get('content-type');
  if (ct && ct.includes('application/json')) return resp.json();
  return null;
};

const mapServerToClientEndpoint = (ep: any): CustomEndpoint => {
  const url = ep?.url || '';
  const isBpjs = url.includes('bpjs-kesehatan.go.') || url.includes('apijkn.bpjs-kesehatan.go.');
  return {
    id: String(ep?.id ?? Date.now().toString()),
    name: ep?.name || '',
    url,
    description: ep?.description || '',
    method: ep?.method || 'GET',
    headers: ep?.headers || ep?.custom_headers || {},
    timeout: Number(ep?.timeout ?? ep?.timeout_seconds ?? 10),
    isActive: Boolean(ep?.isActive ?? ep?.is_active ?? true),
    isBpjsEndpoint: Boolean(ep?.isBpjsEndpoint ?? isBpjs),
    useProxy: Boolean(ep?.useProxy ?? ep?.use_proxy ?? isBpjs)
  };
};

const loadCustomEndpointsFromServer = async () => {
  try {
    const res = await apiFetch('/bpjs-monitoring/custom-endpoints', { method: 'GET' });
    const list = Array.isArray(res?.data) ? res.data : [];
    customEndpoints.value = list.map(mapServerToClientEndpoint);
    saveCustomEndpoints(); 
  } catch (e) {
    console.warn('Gagal load dari server, fallback ke localStorage:', e);
    loadCustomEndpoints();
  }
};


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

const addCustomEndpoint = async () => {
  if (!newEndpoint.value.name || !newEndpoint.value.url) {
    error.value = 'Name and URL are required';
    return;
  }

  
  const normalized = normalizeBpjsUrl(newEndpoint.value.url);
  if (normalized.corrected) {
    error.value = 'Memperbaiki URL: mengganti domain .go.ids menjadi .go.id';
    setTimeout(() => { error.value = null; }, 5000);
  }

  
  const isBpjsEndpoint = normalized.url.includes('apijkn.bpjs-kesehatan.go.id') ||
                         normalized.url.includes('bpjs-kesehatan.go.id') ||
                         normalized.url.includes('new-api.bpjs-kesehatan.go.id') ||
                         normalized.url.includes('apijkn.bpjs-kesehatan.go.') ||
                         normalized.url.includes('bpjs-kesehatan.go.');

  try {
    const payload = {
      name: newEndpoint.value.name,
      url: normalized.url,
      description: newEndpoint.value.description || '',
      method: newEndpoint.value.method || 'GET',
      custom_headers: newEndpoint.value.headers || {},
      timeout_seconds: newEndpoint.value.timeout || 10,
      is_active: newEndpoint.value.isActive !== false,
      use_proxy: isBpjsEndpoint
    };
    const res = await apiFetch('/bpjs-monitoring/custom-endpoints', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    const created = mapServerToClientEndpoint(res?.data);
    customEndpoints.value.push(created);
    saveCustomEndpoints();
  } catch (e) {
    console.error('Gagal simpan ke server, fallback ke localStorage', e);
    const endpoint: CustomEndpoint = {
      id: Date.now().toString(),
      name: newEndpoint.value.name,
      url: normalized.url,
      description: newEndpoint.value.description || '',
      method: newEndpoint.value.method || 'GET',
      headers: newEndpoint.value.headers || {},
      timeout: newEndpoint.value.timeout || 10,
      isActive: newEndpoint.value.isActive !== false,
      isBpjsEndpoint: isBpjsEndpoint,
      useProxy: isBpjsEndpoint
    };
    customEndpoints.value.push(endpoint);
    saveCustomEndpoints();
  }
  
  
  if (isBpjsEndpoint) {
    error.value = 'BPJS endpoint detected! This will be tested via backend proxy with proper authentication.';
    setTimeout(() => { error.value = null; }, 5000);
  }
  
  
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
  editingEndpoint.value = { ...endpoint };
  newEndpoint.value = { ...endpoint };
  showAddEndpointModal.value = true;
};

const editCustomEndpointById = (id?: string) => {
  if (!id) return;
  const ep = customEndpoints.value.find(e => e.id === id);
  if (ep) editCustomEndpoint(ep);
};

const updateCustomEndpoint = async () => {
  if (!editingEndpoint.value || !newEndpoint.value.name || !newEndpoint.value.url) {
    error.value = 'Name and URL are required';
    return;
  }

  const index = customEndpoints.value.findIndex(ep => ep.id === editingEndpoint.value!.id);
  if (index !== -1) {
    
    const normalized = normalizeBpjsUrl(newEndpoint.value.url);
    if (normalized.corrected) {
      error.value = 'Memperbaiki URL: mengganti domain .go.ids menjadi .go.id';
      setTimeout(() => { error.value = null; }, 5000);
    }

    const isBpjsEndpoint = normalized.url.includes('bpjs-kesehatan.go.id') ||
                           normalized.url.includes('apijkn.bpjs-kesehatan.go.id') ||
                           normalized.url.includes('new-api.bpjs-kesehatan.go.id');

    try {
      const payload = {
        name: newEndpoint.value.name,
        url: normalized.url,
        description: newEndpoint.value.description || '',
        method: newEndpoint.value.method || 'GET',
        custom_headers: newEndpoint.value.headers || {},
        timeout_seconds: newEndpoint.value.timeout || 10,
        is_active: newEndpoint.value.isActive !== false,
        use_proxy: isBpjsEndpoint
      };
      const res = await apiFetch(`/bpjs-monitoring/custom-endpoints/${editingEndpoint.value!.id}`, {
        method: 'PUT',
        body: JSON.stringify(payload)
      });
      customEndpoints.value[index] = mapServerToClientEndpoint(res?.data);
      saveCustomEndpoints();
    } catch (e) {
      console.error('Gagal update ke server, simpan lokal', e);
      customEndpoints.value[index] = {
        ...editingEndpoint.value,
        name: newEndpoint.value.name,
        url: normalized.url,
        description: newEndpoint.value.description || '',
        method: newEndpoint.value.method || 'GET',
        headers: newEndpoint.value.headers || {},
        timeout: newEndpoint.value.timeout || 10,
        isActive: newEndpoint.value.isActive !== false,
        isBpjsEndpoint,
        useProxy: isBpjsEndpoint
      };
      saveCustomEndpoints();
    }
    closeAddEndpointModal();
    fetchMonitoringData();
  }
};

const deleteCustomEndpoint = async (id: string) => {
  if (confirm('Are you sure you want to delete this endpoint?')) {
    try {
      await apiFetch(`/bpjs-monitoring/custom-endpoints/${id}`, { method: 'DELETE' });
    } catch (e) {
      console.warn('Gagal hapus di server, lanjut hapus lokal:', e);
    }
    customEndpoints.value = customEndpoints.value.filter(ep => ep.id !== id);
    saveCustomEndpoints();
    fetchMonitoringData();
  }
};

const toggleEndpointStatus = async (id: string) => {
  const endpoint = customEndpoints.value.find(ep => ep.id === id);
  if (endpoint) {
    endpoint.isActive = !endpoint.isActive;
    try {
      await apiFetch(`/bpjs-monitoring/custom-endpoints/${id}`, {
        method: 'PUT',
        body: JSON.stringify({ is_active: endpoint.isActive })
      });
    } catch (e) {
      console.warn('Gagal toggle di server, simpan lokal:', e);
    }
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


const testCustomEndpoint = async (endpoint: CustomEndpoint): Promise<EndpointData> => {
  const start = performance.now();
  
  try {
    let response;
    
    
    
    if (endpoint.isBpjsEndpoint || endpoint.method === 'PING') {
      
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
        signal: AbortSignal.timeout((endpoint.timeout || 10) * 1000 + 5000) 
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
    
    
    let message = 'Network error';
    let status: 'error' | 'timeout' = 'error';
    
    if (error?.name === 'TimeoutError') {
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
    
    
    const customResults: EndpointData[] = [];
    const activeCustomEndpoints = customEndpoints.value
      .filter((ep: any) => ep && typeof ep === 'object' && ep.isActive && !IGNORED_ENDPOINT_NAMES.has(ep.name));
    
    if (activeCustomEndpoints.length > 0) {
      const customTests = await Promise.allSettled(
        activeCustomEndpoints.map(endpoint => testCustomEndpoint(endpoint))
      );
      
      customTests.forEach((result, index) => {
        if (result.status === 'fulfilled') {
          customResults.push(result.value);
        } else {
          
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
    
    
    const defaultEndpoints: EndpointData[] = Array.isArray(data.endpoints)
      ? data.endpoints.filter((ep: any) => ep && typeof ep === 'object' && typeof ep.name === 'string' && !IGNORED_ENDPOINT_NAMES.has(ep.name))
      : [];

    
    
    const combined = [...defaultEndpoints];
    const nameSet = new Set<string>(defaultEndpoints.map(ep => (ep.name || '').trim().toLowerCase()));
    for (const ep of customResults) {
      const key = (ep.name || '').trim().toLowerCase();
      if (!nameSet.has(key)) {
        nameSet.add(key);
        combined.push(ep);
      }
    }
    const allEndpoints = combined;
    
    
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

    
    const label = new Date(data.timestamp).toLocaleTimeString();
    chartLabels.value.push(label);
    chartValues.value.push(Math.round(monitoringData.value.summary.avg_response_time));
    if (chartLabels.value.length > maxPoints.value) {
      chartLabels.value.shift();
      chartValues.value.shift();
    }

    
    const endpointMap = new Map<string, { label: string; value: number }>();
    allEndpoints.forEach((ep) => {
      const key = ep.customId || ep.name || ep.url;
      const labelName = ep.name || ep.url || 'Unknown';
      endpointMap.set(key, { label: labelName, value: Math.round(ep.response_time) });
    });

    
    chartDatasets.value.forEach((ds) => {
      const entry = endpointMap.get(ds.id);
      ds.data.push(entry ? entry.value : null);
      if (ds.data.length > maxPoints.value) ds.data.shift();
    });

    
    const existingKeys = new Set(chartDatasets.value.map((ds) => ds.id));
    endpointMap.forEach((entry, key) => {
      if (!existingKeys.has(key)) {
        const initialGaps = Array(Math.max(0, chartLabels.value.length - 1)).fill(null);
        chartDatasets.value.push({
          id: key,
          label: entry.label,
          data: [...initialGaps, entry.value],
          borderColor: getColorForId(key),
          pointRadius: 2,
          tension: 0.25,
        });
      }
    });

    
    const ts = data.timestamp;
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

    
    updateIsDarkChart();
    
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
  const rt = Math.round(responseTime);
  if (rt < 1000) return 'text-green-600';
  if (rt < 2000) return 'text-yellow-600';
  return 'text-red-600';
};




const getResponseBadgeClass = (responseTime: number) => {
  const rt = Math.round(responseTime);
  if (rt < 1000) {
    return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
  }
  
  return 'inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800';
};




const getHeartbeatDotClass = (h: EndpointHistoryEntry) => {
  const rt = Math.round(h.response_time);
  if (h.status === 'success') {
    if (rt >= 1000) return 'bg-yellow-500';
    return 'bg-green-500';
  }
  if (h.status === 'timeout' || h.status === 'error') return 'bg-red-500';
  return 'bg-gray-500';
};

const formatTime = (timeString: string) => {
  return new Date(timeString).toLocaleTimeString();
};

const getBadgeClass = (status: string) => {
  switch (status) {
    case 'success':
      return 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800';
    case 'timeout':
      
      return 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800';
    case 'error':
      
      return 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800';
    default:
      return 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800';
  }
};

onMounted(async () => {
  await loadCustomEndpointsFromServer();
  await fetchMonitoringData();
  const startInterval = () => {
    if (intervalId) clearInterval(intervalId);
    intervalId = window.setInterval(() => {
      if (!isPaused.value) fetchMonitoringData();
    }, refreshIntervalMs.value);
  };
  (fetchMonitoringData as any)._startInterval = startInterval;
  startInterval();
  updateIsDarkChart();
  const mql = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const handler = () => updateIsDarkChart();
  if (mql) mql.addEventListener('change', handler);
  
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


watch(refreshIntervalMs, () => {
  const starter = (fetchMonitoringData as any)._startInterval as (() => void) | undefined;
  if (!isPaused.value && starter) starter();
});

watch(isPaused, (paused) => {
  if (paused) {
    if (intervalId) { clearInterval(intervalId); intervalId = null; }
  } else {
    const starter = (fetchMonitoringData as any)._startInterval as (() => void) | undefined;
    if (starter) starter();
  }
});

watch(maxPoints, (newMax) => {
  const n = Math.max(10, Math.min(200, Number(newMax) || 50));
  if (chartLabels.value.length > n) chartLabels.value.splice(0, chartLabels.value.length - n);
  if (chartValues.value.length > n) chartValues.value.splice(0, chartValues.value.length - n);
  chartDatasets.value.forEach((ds) => {
    if (ds.data.length > n) ds.data.splice(0, ds.data.length - n);
  });
});


const expandedKeys = ref<string[]>([]);
const endpointHistories = ref<Record<string, EndpointHistoryEntry[]>>({});

const getEndpointKey = (ep: EndpointData, idx?: number) => ep.customId || ep.name || ep.url || String(idx ?? '0');
const isExpanded = (key: string) => expandedKeys.value.includes(key);
const toggleExpanded = (key: string) => {
  const i = expandedKeys.value.indexOf(key);
  if (i >= 0) expandedKeys.value.splice(i, 1); else expandedKeys.value.push(key);
};

const getDatasetForKey = (key: string): LineDataset | undefined => chartDatasets.value.find(ds => ds.id === key);
const getSingleDataset = (key: string): LineDataset[] => {
  const ds = getDatasetForKey(key);
  return ds ? [{ id: ds.id, label: ds.label, data: ds.data, borderColor: ds.borderColor, pointRadius: 2, tension: 0.25 }] : [];
};

const getAvgPingForKey = (key: string): number | null => {
  const ds = getDatasetForKey(key);
  if (!ds) return null;
  const vals = ds.data.filter((v) => typeof v === 'number') as number[];
  if (!vals.length) return null;
  return Math.round(vals.reduce((a, b) => a + b, 0) / vals.length);
};

const getUptimePercentageForKey = (key: string): number | null => {
  const history = endpointHistories.value[key] || [];
  if (!history.length) return null;
  const ok = history.filter(h => h.status === 'success').length;
  return Math.round((ok / history.length) * 10000) / 100; 
};

const clearEndpointHistory = (key: string) => {
  endpointHistories.value[key] = [];
};


const openUptimeKuma = () => {
  window.open('http://192.168.0.2:3001', '_blank', 'noopener,noreferrer');
};


const selectedKey = ref<string | null>(null);
const selectEndpoint = (key: string) => { selectedKey.value = key; };
const getEndpointByKey = (key: string): { endpoint: EndpointData; idx: number } | null => {
  const eps = safeEndpoints.value;
  for (let i = 0; i < eps.length; i++) {
    const k = getEndpointKey(eps[i], i);
    if (k === key) return { endpoint: eps[i], idx: i };
  }
  return null;
};
const selected = computed(() => (selectedKey.value ? getEndpointByKey(selectedKey.value) : null));


watch(safeEndpoints, (eps) => {
  if (!selectedKey.value && eps.length) {
    selectedKey.value = getEndpointKey(eps[0], 0);
  }
}, { immediate: true });
</script>

<template>
  <Head title="BPJS Monitoring Dashboard" />
  <AppHeaderLayout :breadcrumbs="breadcrumbs" fluid hideHeader>
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            MonPi Endpoint
          </h1>
          <!-- <p class="text-gray-600 dark:text-gray-400 mt-1">
            Real-time monitoring dashboard untuk konektivitas API BPJS
          </p> -->
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ headerStatusCopy }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Last updated: {{ lastUpdate }} <!--• {{ refreshCopy }} -->
          </p>
        </div>
        <div class="flex items-center gap-4">
          <!-- Desktop actions -->
          <div class="hidden md:flex items-center gap-4">
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
            <Button 
              @click="openUptimeKuma" 
              variant="outline"
              size="sm"
            >
              <ExternalLink class="w-4 h-4 mr-2" />
              Uptime Kuma
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
                <DropdownMenuItem @click="openUptimeKuma">
                  <ExternalLink class="w-4 h-4 mr-2" />
                  Kuma
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="fetchMonitoringData" :disabled="isLoading">
                  <RefreshCw :class="{ 'animate-spin': isLoading }" class="w-4 h-4 mr-2" />
                  Refresh
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
          <!-- Theme toggle mobile dihapus; gunakan FloatingThemeToggle di pojok bawah -->
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

      <!-- Main Content: grid dengan sidebar kiri dan area kanan -->
      <div v-if="monitoringData" class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6">
        <!-- Sidebar kiri: daftar endpoint -->
        <Card class="hidden md:block lg:sticky lg:top-6 self-start">
          <CardHeader>
            <CardTitle>Monitors</CardTitle>
            <CardDescription>Daftar endpoint</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div
                v-for="(endpoint, idx) in safeEndpoints"
                :key="getEndpointKey(endpoint, idx)"
                @click="selectEndpoint(getEndpointKey(endpoint, idx))"
                :class="[
                  'p-3 border rounded-lg cursor-pointer transition-colors',
                  selectedKey === getEndpointKey(endpoint, idx)
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'
                ]"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <div :class="getStatusColor(endpoint.status)" class="w-2.5 h-2.5 rounded-full"></div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[180px] md:max-w-[220px] lg:max-w-[300px]">
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

        <!-- Area kanan: summary, chart, dan detail endpoint -->
        <div class="flex flex-col space-y-6">
          <!-- Summary Cards: Mobile slider -->
          <div class="md:hidden order-1">
            <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-2">
              <!-- Total Endpoints -->
              <Card class="min-w-full snap-start">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Total Endpoints</CardTitle>
                  <Activity class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ monitoringData.summary.total }}</div>
                  <p class="text-xs text-muted-foreground mt-1">Monitored endpoints</p>
                </CardContent>
              </Card>

              <!-- Uptime -->
              <Card class="min-w-full snap-start">
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

              <!-- Avg Response Time -->
              <Card class="min-w-full snap-start">
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

              <!-- Status -->
              <Card class="min-w-full snap-start">
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
          </div>

          <!-- Monitors: Mobile-only copy placed right under slide card -->
          <Card class="md:hidden order-2">
            <CardHeader>
              <CardTitle>Monitors</CardTitle>
              <CardDescription>Daftar endpoint</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="space-y-2">
                <div
                  v-for="(endpoint, idx) in safeEndpoints"
                  :key="getEndpointKey(endpoint, idx)"
                  @click="selectEndpoint(getEndpointKey(endpoint, idx))"
                  :class="[
                    'p-3 border rounded-lg cursor-pointer transition-colors',
                    selectedKey === getEndpointKey(endpoint, idx)
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                      : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'
                  ]"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <div :class="getStatusColor(endpoint.status)" class="w-2.5 h-2.5 rounded-full"></div>
                      <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[260px]">
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

          <!-- Summary Cards: Desktop grid -->
          <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-4 gap-6">
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

          <!-- Real-time Line Chart -->
          <Card class="order-4 md:order-none">
            <CardHeader>
              <CardTitle>Response Time (Real-time)</CardTitle>
              <CardDescription>
                Refresh every {{ Math.round(refreshIntervalMs / 1000) }} sec
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div class="flex flex-wrap items-center gap-3 mb-3">
                <label class="text-sm text-gray-600 dark:text-gray-300">Interval</label>
                <select v-model.number="refreshIntervalMs" class="px-2 py-1 border rounded text-sm">
                  <option :value="10000">10s</option>
                  <option :value="20000">20s</option>
                  <option :value="30000">30s</option>
                  <option :value="60000">60s</option>
                </select>

                <Button @click="isPaused = !isPaused" size="sm" variant="outline">
                  {{ isPaused ? 'Resume' : 'Pause' }}
                </Button>

                <label class="text-sm text-gray-600 dark:text-gray-300">Max Points</label>
                <input type="number" v-model.number="maxPoints" min="10" max="200" class="w-20 px-2 py-1 border rounded text-sm" />

                <label class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                  <input type="checkbox" v-model="showTopNSlowest" />
                  Top N slowest
                </label>
                <input v-if="showTopNSlowest" type="number" v-model.number="topN" min="1" max="20" class="w-16 px-2 py-1 border rounded text-sm" />

                <!-- <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-600 dark:text-gray-300">Filter endpoints</span>
                  <select multiple v-model="activeEndpointIds" class="min-w-[200px] max-w-[320px] px-2 py-1 border rounded text-sm">
                    <option v-for="ep in availableEndpoints" :key="ep.id" :value="ep.id">{{ ep.label }}</option>
                  </select>
                </div> -->
              </div>
              <RealtimeLineChart :labels="chartLabels" :datasets="displayDatasets" :dark="isDarkChart" />
            </CardContent>
          </Card>

          <!-- Detail Endpoint -->
          <Card class="order-3 md:order-none">
            <CardHeader>
              <CardTitle>Detail Endpoint</CardTitle>
              <CardDescription v-if="selected">Status dan performa terkini</CardDescription>
            </CardHeader>
            <CardContent v-if="selected">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ selected.endpoint.name || selected.endpoint.url || 'Unknown' }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    Kode: {{ selected.endpoint.code }} • {{ selected.endpoint.message }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="font-medium" :class="getResponseTimeColor(selected.endpoint.response_time)">
                    {{ Math.round(selected.endpoint.response_time) }} ms
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Status: {{ selected.endpoint.status }}</div>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                  <div class="text-xs text-gray-600 dark:text-gray-300">Ping</div>
                  <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ Math.round(selected.endpoint.response_time) }} ms</div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Current</p>
                </div>
                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                  <div class="text-xs text-gray-600 dark:text-gray-300">Avg. Ping</div>
                  <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedKey ? (getAvgPingForKey(selectedKey) ?? '-') : '-' }} ms</div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Session</p>
                </div>
                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                  <div class="text-xs text-gray-600 dark:text-gray-300">Uptime</div>
                  <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedKey ? (getUptimePercentageForKey(selectedKey) ?? 0) : 0 }}%</div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Session checks</p>
                </div>
                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                  <div class="text-xs text-gray-600 dark:text-gray-300">Severity</div>
                  <div class="text-sm font-semibold" :class="getResponseTimeColor(selected.endpoint.response_time)">{{ selected.endpoint.severity || '-' }}</div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Code: {{ selected.endpoint.code }}</p>
                </div>
              </div>

              <div class="mt-4">
                <RealtimeLineChart :labels="chartLabels" :datasets="selectedKey ? getSingleDataset(selectedKey) : []" :dark="isDarkChart" />
              </div>

              <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-gray-900 dark:text-white">Status History</span>
                  <Button size="sm" variant="outline" @click.stop="selectedKey && clearEndpointHistory(selectedKey)">Clear Data</Button>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-300">
                  <div v-for="h in (selectedKey ? (endpointHistories[selectedKey] || []).slice(0, 10) : [])" :key="h.timestamp + String(h.code)" class="flex items-center justify-between gap-2 py-1 border-b border-gray-100 dark:border-gray-800">
                    <span :class="getBadgeClass(h.status)">{{ h.status }}</span>
                    <span
                      class="text-xs font-mono"
                      :class="h.status === 'success' ? getResponseBadgeClass(h.response_time) : getBadgeClass(h.status)"
                    >
                      {{ Math.round(h.response_time) }} ms
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">{{ h.timestamp }}</span>
                    <span class="text-gray-700 dark:text-gray-300 truncate max-w-[50%]">{{ h.message }}</span>
                  </div>
                </div>
              </div>
            </CardContent>
            <CardContent v-else>
              <p class="text-sm text-gray-500 dark:text-gray-400">Pilih endpoint di panel kiri untuk melihat detail.</p>
            </CardContent>
          </Card>
        </div>
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
                  <option value="PING">PING</option>
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
              v-for="endpoint in customEndpoints.filter(Boolean)" 
              :key="endpoint.id"
              class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg"
            >
              <div class="flex-1">
                <div class="flex items-center space-x-2">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ endpoint.name || endpoint.url || 'Unknown' }}
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
    <FloatingThemeToggle />
</AppHeaderLayout>
</template>

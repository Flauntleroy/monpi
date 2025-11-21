<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuCheckboxItem } from '@/components/ui/dropdown-menu';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { 
  Calendar,
  Thermometer,
  Droplets,
  TrendingUp,
  Download,
  RefreshCw,
  AlertTriangle,
  Sunrise,
  Sunset,
  MoreVertical,
  Sun,
  Moon,
  Home
} from 'lucide-vue-next';
import Chart from 'chart.js/auto';
import * as XLSX from 'xlsx';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

interface MorningReading {
  time: string;
  temperature_c: number;
  humidity: number;
}

interface EveningReading {
  time: string;
  temperature_c: number;
  humidity: number;
}

interface DailyData {
  date: string;
  avg_temperature: number | null;
  avg_humidity: number | null;
  readings_count: number;
  morning_reading: MorningReading | null;
  evening_reading: EveningReading | null;
}

interface RecordDetail {
  temperature_c: number;
  humidity: number;
  recorded_at: string;
  device_id: string;
}

interface OverallStats {
  max_temperature: number;
  min_temperature: number;
  max_humidity: number;
  min_humidity: number;
  avg_temperature: number;
  avg_humidity: number;
  total_readings: number;
  max_temp_record?: RecordDetail;
  min_temp_record?: RecordDetail;
  max_humidity_record?: RecordDetail;
  min_humidity_record?: RecordDetail;
}

interface ReportData {
  meta: {
    month: number;
    year: number;
    device_id: string | null;
  };
  data: DailyData[];
  overall_stats?: OverallStats;
}

const reportData = ref<ReportData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

// Form inputs
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());
const selectedDevice = ref<string | null>(null);

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const years = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 5 }, (_, i) => currentYear - i);
});

// Selected day for detail modal
const selectedDay = ref<DailyData | null>(null);

// Selected record for max/min temperature detail modal
interface SelectedRecordData {
  type: string;
  record: RecordDetail;
}
const selectedRecord = ref<SelectedRecordData | null>(null);

// Calendar helper functions
const getFirstDayOfMonth = () => {
  if (!reportData.value || reportData.value.data.length === 0) return 0;
  const firstDate = new Date(reportData.value.data[0].date);
  const dayOfWeek = firstDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
  return dayOfWeek; // Return number of empty cells needed
};

const openDayDetail = (day: DailyData) => {
  selectedDay.value = day;
};

const getTotalReadings = () => {
  if (!reportData.value) return 0;
  return reportData.value.data.reduce((total, day) => total + (day.readings_count || 0), 0);
};

// Theme appearance API
const { updateAppearance } = useAppearance();
// Track applied theme so toggle reflects actual state (including 'system')
const isDark = ref(false);
const updateIsDark = () => {
  if (typeof document !== 'undefined') {
    isDark.value = document.documentElement.classList.contains('dark');
  }
};

const toggleTheme = () => {
  const next = isDark.value ? 'light' : 'dark';
  updateAppearance(next);
  isDark.value = next === 'dark';
};

const fetchReport = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    
    const params = new URLSearchParams();
    params.set('month', String(selectedMonth.value));
    params.set('year', String(selectedYear.value));
    if (selectedDevice.value) params.set('device_id', selectedDevice.value);
    
    const response = await fetch(`/sensor/report?${params.toString()}`);
    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    reportData.value = data;
  } catch (err: any) {
    error.value = err?.message || 'Gagal memuat report';
    console.error('Report error:', err);
  } finally {
    isLoading.value = false;
  }
};

const getTempColor = (temp: number | null) => {
  if (temp === null) return 'text-gray-400';
  if (temp < 20) return 'text-blue-600 dark:text-blue-400';
  if (temp > 30) return 'text-red-600 dark:text-red-400';
  return 'text-green-600 dark:text-green-400';
};

const getHumidityColor = (humidity: number | null) => {
  if (humidity === null) return 'text-gray-400';
  if (humidity < 30) return 'text-orange-600 dark:text-orange-400';
  if (humidity > 70) return 'text-blue-600 dark:text-blue-400';
  return 'text-green-600 dark:text-green-400';
};

const formatDate = (dateStr: string) => {
  const date = new Date(dateStr);
  const day = date.getDate();
  const monthName = monthNames[date.getMonth()];
  return `${day} ${monthName}`;
};

const formatRecordDate = (dateStr: string) => {
  const date = new Date(dateStr);
  const day = date.getDate();
  const monthName = monthNames[date.getMonth()];
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  
  return {
    date: `${day} ${monthName} ${year}`,
    time: `${hours}:${minutes} WITA`
  };
};

const openRecordDetail = (type: string, record: RecordDetail) => {
  selectedRecord.value = { type, record };
};

const downloadCSV = () => {
  if (!reportData.value) return;
  
  const headers = ['Tanggal', 'Rata-rata Suhu (°C)', 'Rata-rata Kelembaban (%)', 'Jumlah Data', 'Suhu Pagi 08:00', 'Kelembaban Pagi', 'Waktu Pagi', 'Suhu Sore 16:00', 'Kelembaban Sore', 'Waktu'];
  const rows = reportData.value.data.map(d => [
    d.date,
    d.avg_temperature?.toFixed(2) || '-',
    d.avg_humidity?.toFixed(2) || '-',
    d.readings_count,
    d.morning_reading?.temperature_c.toFixed(2) || '-',
    d.morning_reading?.humidity.toFixed(2) || '-',
    d.morning_reading?.time || '-',
    d.evening_reading?.temperature_c.toFixed(2) || '-',
    d.evening_reading?.humidity.toFixed(2) || '-',
    d.evening_reading?.time || '-',
  ]);
  
  const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `sensor-report-${selectedYear.value}-${selectedMonth.value}.csv`;
  a.click();
  URL.revokeObjectURL(url);
};

const downloadXLSX = () => {
  if (!reportData.value) return;

  const headers = ['Tanggal', 'Rata-rata Suhu (°C)', 'Rata-rata Kelembaban (%)', 'Jumlah Data', 'Suhu Pagi 08:00', 'Kelembaban Pagi', 'Waktu Pagi', 'Suhu Sore 16:00', 'Kelembaban Sore', 'Waktu Sore'];
  const rows = reportData.value.data.map(d => [
    d.date,
    d.avg_temperature?.toFixed(2) || '-',
    d.avg_humidity?.toFixed(2) || '-',
    d.readings_count,
    d.morning_reading?.temperature_c.toFixed(2) || '-',
    d.morning_reading?.humidity.toFixed(2) || '-',
    d.morning_reading?.time || '-',
    d.evening_reading?.temperature_c.toFixed(2) || '-',
    d.evening_reading?.humidity.toFixed(2) || '-',
    d.evening_reading?.time || '-',
  ]);

  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  XLSX.utils.book_append_sheet(wb, ws, 'Sensor Report');
  const month = String(selectedMonth.value).padStart(2, '0');
  XLSX.writeFile(wb, `sensor-report-${selectedYear.value}-${month}.xlsx`);
};

// Generate chart image (line chart: avg temperature & humidity per day)
const generateReportChartImage = async (): Promise<string | null> => {
  if (!reportData.value) return null;
  const canvas = document.createElement('canvas');
  // Set fixed size for high-quality image
  canvas.width = 1000;
  canvas.height = 400;
  const ctx = canvas.getContext('2d');
  if (!ctx) return null;

  const labels = reportData.value.data.map(d => {
    const date = new Date(d.date);
    return `${String(date.getDate()).padStart(2, '0')}`;
  });
  const temps = reportData.value.data.map(d => d.avg_temperature ?? null);
  const humids = reportData.value.data.map(d => d.avg_humidity ?? null);

  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Avg Temperature (°C)',
          data: temps,
          borderColor: 'rgba(239, 68, 68, 0.9)',
          backgroundColor: 'rgba(239, 68, 68, 0.2)',
          tension: 0.3,
          fill: true,
          spanGaps: true,
        },
        {
          label: 'Avg Humidity (%)',
          data: humids,
          borderColor: 'rgba(59, 130, 246, 0.9)',
          backgroundColor: 'rgba(59, 130, 246, 0.2)',
          tension: 0.3,
          fill: true,
          spanGaps: true,
        },
      ],
    },
    options: {
      responsive: false,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: true, position: 'top' },
        title: { display: false },
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } },
      },
    },
  });

  // Allow the chart to render
  await new Promise((resolve) => setTimeout(resolve, 100));
  const dataUrl = canvas.toDataURL('image/png');
  chart.destroy();
  return dataUrl;
};

// Compute overall averages for temperature and humidity
const computeOverallStats = () => {
  if (!reportData.value) return { avgTemp: 0, avgHumidity: 0, totalDays: 0 };
  let tempSum = 0;
  let tempCount = 0;
  let humidSum = 0;
  let humidCount = 0;
  reportData.value.data.forEach(d => {
    if (typeof d.avg_temperature === 'number') {
      tempSum += d.avg_temperature;
      tempCount += 1;
    }
    if (typeof d.avg_humidity === 'number') {
      humidSum += d.avg_humidity;
      humidCount += 1;
    }
  });
  return {
    avgTemp: tempCount ? tempSum / tempCount : 0,
    avgHumidity: humidCount ? humidSum / humidCount : 0,
    totalDays: reportData.value.data.length,
  };
};

const downloadPDF = async () => {
  if (!reportData.value) return;

  const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
  const month = String(selectedMonth.value).padStart(2, '0');
  const title = 'Laporan Harian Data Suhu dan Kelembaban Unit IT.';

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 40;

  // Header background
  const headerHeight = 70;
  doc.setFillColor(40, 110, 180);
  doc.rect(0, 0, pageWidth, headerHeight, 'F');

  // Header text
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(18);
  doc.text(title, margin, 28);
  doc.setFontSize(11);
  const subtitle = selectedDevice.value ? `Device: ${selectedDevice.value}` : '${selectedYear.value}-${month}';
  doc.text(subtitle, margin, 48);

  // Summary stats boxes
  doc.setTextColor(0, 0, 0);
  const statsY = headerHeight + 20;
  const boxWidth = (pageWidth - margin * 2 - 20) / 2;
  const boxHeight = 60;
  const stats = computeOverallStats();

  // Box 1 - Temperature
  doc.setFillColor(240, 248, 255);
  doc.roundedRect(margin, statsY, boxWidth, boxHeight, 8, 8, 'F');
  doc.setFontSize(11);
  doc.text('Average Temperature', margin + 12, statsY + 22);
  doc.setFontSize(16);
  doc.setTextColor(239, 68, 68);
  doc.text(`${stats.avgTemp.toFixed(2)}°C`, margin + 12, statsY + 44);
  doc.setTextColor(0, 0, 0);

  // Box 2 - Humidity
  const box2X = margin + boxWidth + 20;
  doc.setFillColor(240, 248, 255);
  doc.roundedRect(box2X, statsY, boxWidth, boxHeight, 8, 8, 'F');
  doc.setFontSize(11);
  doc.text('Average Humidity', box2X + 12, statsY + 22);
  doc.setFontSize(16);
  doc.setTextColor(59, 130, 246);
  doc.text(`${stats.avgHumidity.toFixed(2)}%`, box2X + 12, statsY + 44);
  doc.setTextColor(0, 0, 0);

  // Chart image
  const chartY = statsY + boxHeight + 20;
  const chartImg = await generateReportChartImage();
  const chartHeight = 200;
  if (chartImg) {
    doc.addImage(chartImg, 'PNG', margin, chartY, pageWidth - margin * 2, chartHeight);
  }

  // Table data
  const headers = [['Tanggal', 'Avg Suhu (°C)', 'Avg Kelembaban (%)', 'Jumlah', 'Pagi 08:00', 'Sore 16:00']];
  const body = reportData.value.data.map(d => [
    d.date,
    d.avg_temperature?.toFixed(2) || '-',
    d.avg_humidity?.toFixed(2) || '-',
    String(d.readings_count),
    d.morning_reading ? `${d.morning_reading.temperature_c.toFixed(2)}°C / ${d.morning_reading.humidity.toFixed(2)}%` : '-',
    d.evening_reading ? `${d.evening_reading.temperature_c.toFixed(2)}°C / ${d.evening_reading.humidity.toFixed(2)}%` : '-',
  ]);

  autoTable(doc, {
    head: headers,
    body,
    startY: chartY + chartHeight + 20,
    styles: { fontSize: 9, cellPadding: 4 },
    headStyles: { fillColor: [40, 110, 180], textColor: 255 },
    theme: 'striped',
  });

  doc.save(`sensor-report-${selectedYear.value}-${month}.pdf`);
};

const overallStats = computed(() => {
  if (!reportData.value) return null;
  
  // If backend provides overall_stats, use it
  if (reportData.value.overall_stats) {
    return {
      avgTemp: reportData.value.overall_stats.avg_temperature,
      avgHumidity: reportData.value.overall_stats.avg_humidity,
      maxTemp: reportData.value.overall_stats.max_temperature,
      minTemp: reportData.value.overall_stats.min_temperature,
      maxHumidity: reportData.value.overall_stats.max_humidity,
      minHumidity: reportData.value.overall_stats.min_humidity,
      totalDays: reportData.value.data.filter(d => d.avg_temperature !== null).length,
    };
  }
  
  // Fallback to client-side calculation
  const validDays = reportData.value.data.filter(d => d.avg_temperature !== null);
  if (validDays.length === 0) return null;
  
  const avgTemp = validDays.reduce((sum, d) => sum + (d.avg_temperature || 0), 0) / validDays.length;
  const avgHumidity = validDays.reduce((sum, d) => sum + (d.avg_humidity || 0), 0) / validDays.length;
  const maxTemp = Math.max(...validDays.map(d => d.avg_temperature || 0));
  const minTemp = Math.min(...validDays.map(d => d.avg_temperature || 0));
  const maxHumidity = Math.max(...validDays.map(d => d.avg_humidity || 0));
  const minHumidity = Math.min(...validDays.map(d => d.avg_humidity || 0));
  
  return {
    avgTemp,
    avgHumidity,
    maxTemp,
    minTemp,
    maxHumidity,
    minHumidity,
    totalDays: validDays.length,
  };
});

onMounted(() => {
  fetchReport();
});

// Theme tracking lifecycle
onMounted(() => {
  updateIsDark();
  const mql = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const handler = () => updateIsDark();
  if (mql) {
    mql.addEventListener('change', handler);
  }
  (isDark as any)._mql = mql;
  (isDark as any)._handler = handler;
});

onUnmounted(() => {
  const mql = (isDark as any)._mql as MediaQueryList | null;
  const handler = (isDark as any)._handler as (() => void) | null;
  if (mql && handler) {
    mql.removeEventListener('change', handler as EventListener);
  }
});
</script>

<template>
  <Head title="Sensor Report" />
  <div class="min-h-screen bg-white dark:bg-gray-900">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="mb-2 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Report </h1>
          <!-- <p class="text-gray-600 dark:text-gray-400 mt-1">Laporan harian suhu dan kelembaban sensor</p> -->
        </div>
        <!-- Actions Dropdown -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button 
              variant="outline" 
              size="sm"
              class="h-10 w-10 p-0 inline-flex items-center justify-center"
              aria-label="Actions"
            >
              <MoreVertical class="w-5 h-5" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem @click="toggleTheme">
              <div class="flex items-center justify-between w-full">
                <div class="flex items-center">
                  <component :is="isDark ? Moon : Sun" class="w-4 h-4 mr-2" />
                  <span>Theme</span>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="isDark"
                  @click.stop="toggleTheme"
                  class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                  :class="isDark ? 'bg-blue-600' : 'bg-gray-300'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="isDark ? 'translate-x-5' : 'translate-x-1'"
                  />
                </button>
              </div>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem :as-child="true">
              <Link class="block w-full" :href="'/sensor'" as="button">
                <Home class="w-4 h-4 mr-2" />
                Home
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center">
          <AlertTriangle class="w-5 h-5 text-red-500 mr-3" />
          <div>
            <h3 class="font-semibold text-red-700 dark:text-red-300">Error</h3>
            <p class="text-red-600 dark:text-red-400 text-sm">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading && !reportData" class="flex items-center justify-center h-64">
        <div class="text-center">
          <RefreshCw class="w-12 h-12 animate-spin mx-auto mb-4 text-blue-500" />
          <p class="text-gray-600 dark:text-gray-400">Memuat data report...</p>
        </div>
      </div>

      <!-- Report Content -->
      <div v-if="reportData && !isLoading" class="space-y-6">
        <!-- Desktop: 2 Column Layout, Mobile: Stack -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column: Filter & Stats (1/3 width on desktop) -->
          <div class="space-y-4">
            <!-- Filter Card - Compact -->
            <Card>
              <CardHeader class="pb-3 pt-4">
                <CardTitle class="text-base flex items-center gap-2">
                  <TrendingUp class="w-4 h-4" />
                  Periode
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-3">
                <div>
                  <label class="text-xs font-medium mb-1 block text-gray-600 dark:text-gray-400">Bulan</label>
                  <select 
                    v-model.number="selectedMonth" 
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  >
                    <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">
                      {{ name }}
                    </option>
                  </select>
                </div>
                
                <div>
                  <label class="text-xs font-medium mb-1 block text-gray-600 dark:text-gray-400">Tahun</label>
                  <select 
                    v-model.number="selectedYear" 
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  >
                    <option v-for="year in years" :key="year" :value="year">
                      {{ year }}
                    </option>
                  </select>
                </div>
                
                <div class="flex gap-2 pt-2">
                  <Button 
                    @click="fetchReport" 
                    :disabled="isLoading"
                    class="flex-1 h-9 text-sm"
                  >
                    <RefreshCw :class="{ 'animate-spin': isLoading }" class="w-3 h-3 mr-1" />
                    {{ isLoading ? 'Loading...' : 'Tampilkan' }}
                  </Button>
                  
                  <!-- Download button with format choices -->
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button 
                        :disabled="!reportData || isLoading"
                        variant="outline"
                        class="h-9 px-3 text-sm"
                        title="Download"
                      >
                        <Download class="w-4 h-4 mr-2" />Export
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-40">
                      <DropdownMenuItem @click="downloadPDF">
                        <Download class="w-4 h-4 mr-2" />
                        PDF
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="downloadXLSX">
                        <Download class="w-4 h-4 mr-2" />
                        XLSX
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </CardContent>
            </Card>

            <!-- Summary Stats - Vertical Stack on Desktop, Horizontal Scroll on Mobile -->
            <div v-if="overallStats">
              <!-- Mobile: Horizontal Scroll -->
              <div class="lg:hidden overflow-x-auto pb-2 -mx-2 px-2">
                <div class="flex gap-3 min-w-max">
                  <!-- Suhu Tertinggi -->
                  <Card 
                    class="w-[calc(48vw-1.4rem)] cursor-pointer hover:shadow-lg transition-shadow" 
                    @click="reportData?.overall_stats?.max_temp_record && openRecordDetail('Max Temperature', reportData.overall_stats.max_temp_record)"
                  >
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <Thermometer class="w-3 h-3" />
                        <span>Max Temperature</span>
                      </div>
                      <div class="text-xl font-bold text-red-500">{{ overallStats.maxTemp.toFixed(1) }}°C</div>
                      <!-- <div class="text-[8px] text-gray-500 dark:text-gray-400 mt-0.5">Detail</div> -->
                    </CardContent>
                  </Card>

                  <!-- Suhu Terendah -->
                  <Card 
                    class="w-[calc(48vw-1.4rem)] cursor-pointer hover:shadow-lg transition-shadow" 
                    @click="reportData?.overall_stats?.min_temp_record && openRecordDetail('Min Temperature', reportData.overall_stats.min_temp_record)"
                  >
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <Thermometer class="w-3 h-3" />
                        <span>Min Temperature</span>
                      </div>
                      <div class="text-xl font-bold text-blue-500">{{ overallStats.minTemp.toFixed(1) }}°C</div>
                      <!-- <div class="text-[8px] text-gray-500 dark:text-gray-400 mt-0.5">Detail</div> -->
                    </CardContent>
                  </Card>
                  <!-- Rata-rata Suhu -->
                  <Card class="w-[calc(48vw-1.4rem)]">
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <Thermometer class="w-3 h-3" />
                        <span>Avg Suhu</span>
                      </div>
                      <div class="text-xl font-bold">{{ overallStats.avgTemp.toFixed(1) }}°C</div>
                    </CardContent>
                  </Card>

                  <!-- Rata-rata Kelembaban -->
                  <Card class="w-[calc(48vw-1.4rem)]">
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <Droplets class="w-3 h-3" />
                        <span>Avg Humid</span>
                      </div>
                      <div class="text-xl font-bold">{{ overallStats.avgHumidity.toFixed(1) }}%</div>
                    </CardContent>
                  </Card>

                  <!-- Total Hari -->
                  <Card class="w-[calc(48vw-1.4rem)]">
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <Calendar class="w-3 h-3" />
                        <span>Total Hari</span>
                      </div>
                      <div class="text-xl font-bold">{{ overallStats.totalDays }}</div>
                    </CardContent>
                  </Card>

                  <!-- Total Pembacaan -->
                  <Card class="w-[calc(48vw-1.4rem)]">
                    <CardContent class="pt-3 pb-3">
                      <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                        <TrendingUp class="w-3 h-3" />
                        <span>Data</span>
                      </div>
                      <div class="text-xl font-bold">{{ getTotalReadings() }}</div>
                    </CardContent>
                  </Card>

                  
                </div>
              </div>

              <!-- Desktop: Vertical Stack -->
              <div class="hidden lg:flex flex-col gap-3">

                <!-- Suhu Tertinggi -->
                <Card 
                  class="cursor-pointer hover:shadow-lg transition-shadow" 
                  @click="reportData?.overall_stats?.max_temp_record && openRecordDetail('Max Temperature', reportData.overall_stats.max_temp_record)"
                >
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <Thermometer class="w-3 h-3" />
                      <span>Max Temperature</span>
                    </div>
                    <div class="text-xl font-bold text-red-500">{{ overallStats.maxTemp.toFixed(1) }}°C</div>
                    <div class="text-[8px] text-gray-500 dark:text-gray-400 mt-0.5">Detail</div>
                  </CardContent>
                </Card>

                <!-- Suhu Terendah -->
                <Card 
                  class="cursor-pointer hover:shadow-lg transition-shadow" 
                  @click="reportData?.overall_stats?.min_temp_record && openRecordDetail('Min Temperature', reportData.overall_stats.min_temp_record)"
                >
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <Thermometer class="w-3 h-3" />
                      <span>Min Temperature</span>
                    </div>
                    <div class="text-xl font-bold text-blue-500">{{ overallStats.minTemp.toFixed(1) }}°C</div>
                    <div class="text-[8px] text-gray-500 dark:text-gray-400 mt-0.5">Detail</div>
                  </CardContent>
                </Card>
                
                <!-- Rata-rata Suhu -->
                <Card>
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <Thermometer class="w-3 h-3" />
                      <span>Avg Suhu</span>
                    </div>
                    <div class="text-xl font-bold">{{ overallStats.avgTemp.toFixed(1) }}°C</div>
                  </CardContent>
                </Card>

                <!-- Rata-rata Kelembaban -->
                <Card>
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <Droplets class="w-3 h-3" />
                      <span>Avg Humid</span>
                    </div>
                    <div class="text-xl font-bold">{{ overallStats.avgHumidity.toFixed(1) }}%</div>
                  </CardContent>
                </Card>

                <!-- Total Hari -->
                <Card>
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <Calendar class="w-3 h-3" />
                      <span>Total Hari</span>
                    </div>
                    <div class="text-xl font-bold">{{ overallStats.totalDays }}</div>
                  </CardContent>
                </Card>

                <!-- Total Pembacaan -->
                <Card>
                  <CardContent class="pt-3 pb-3">
                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400 text-[10px] mb-1">
                      <TrendingUp class="w-3 h-3" />
                      <span>Data</span>
                    </div>
                    <div class="text-xl font-bold">{{ getTotalReadings() }}</div>
                  </CardContent>
                </Card>

                
              </div>
            </div>
          </div>

          <!-- Right Column: Calendar (2/3 width on desktop) -->
          <div class="lg:col-span-2">
            <Card>
              <CardHeader class="pb-3 pt-4">
                <CardTitle class="text-lg">{{ monthNames[selectedMonth - 1] }} {{ selectedYear }}</CardTitle>
                <!-- <CardDescription class="text-xs">Klik tanggal untuk detail</CardDescription> -->
              </CardHeader>
              <CardContent class="pt-0">
                <!-- Calendar Grid - More Compact -->
                <div class="grid grid-cols-7 gap-1.5">
                  <!-- Day Headers -->
                  <div v-for="day in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']" :key="day" class="text-center text-xs font-semibold text-gray-600 dark:text-gray-400 py-1">
                    {{ day }}
                  </div>
                  
                  <!-- Empty cells for days before month starts -->
                  <div v-for="n in getFirstDayOfMonth()" :key="`empty-${n}`" class="aspect-square"></div>
                  
                  <!-- Calendar Days -->
                  <div 
                    v-for="day in reportData.data" 
                    :key="day.date"
                    @click="openDayDetail(day)"
                    class="aspect-square border border-gray-200 dark:border-gray-700 rounded-md p-1.5 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-300 dark:hover:border-blue-700 transition-all"
                    :class="{
                      'bg-gray-50 dark:bg-gray-800': day.avg_temperature === null,
                      'hover:shadow-md': day.avg_temperature !== null
                    }"
                  >
                    <div class="flex flex-col h-full">
                      <div class="text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                        {{ new Date(day.date).getDate() }}
                      </div>
                      <div v-if="day.avg_temperature !== null" class="flex-1 flex flex-col items-center justify-center">
                        <Thermometer class="w-3 h-3 mb-0.5" :class="getTempColor(day.avg_temperature)" />
                        <div class="text-[10px] font-bold" :class="getTempColor(day.avg_temperature)">
                          {{ day.avg_temperature.toFixed(1) }}°
                        </div>
                        <div class="text-[9px] text-gray-500 dark:text-gray-400">
                          {{ day.avg_humidity?.toFixed(0) }}%
                        </div>
                      </div>
                      <div v-else class="flex-1 flex items-center justify-center">
                        <span class="text-[10px] text-gray-400">-</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Legend - Smaller -->
                <div class="mt-3 flex items-center gap-3 text-[10px] text-gray-600 dark:text-gray-400">
                  <div class="flex items-center gap-1">
                    <Thermometer class="w-2.5 h-2.5" />
                    <span>Suhu</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <Droplets class="w-2.5 h-2.5" />
                    <span>Kelembaban</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <!-- Removed floating theme toggle in favor of dropdown -->
    </div>
    
    <!-- Detail Modal -->
    <Dialog :open="selectedDay !== null" @update:open="(v: boolean) => { if (!v) selectedDay = null }">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle v-if="selectedDay">
            Data Sensor {{ formatDate(selectedDay.date) }}
          </DialogTitle>
          <!-- <DialogDescription>
            Informasi lengkap pembacaan sensor
          </DialogDescription> -->
        </DialogHeader>
        
        <div v-if="selectedDay" class="space-y-4">
          <!-- Summary -->
          <div class="grid grid-cols-2 gap-4">
            <Card>
              <CardContent class="pt-4">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 text-sm mb-1">
                  <Thermometer class="w-4 h-4" />
                  <span>Rata-rata Suhu</span>
                </div>
                <div class="text-2xl font-bold" :class="getTempColor(selectedDay.avg_temperature)">
                  {{ selectedDay.avg_temperature !== null ? selectedDay.avg_temperature.toFixed(1) + '°C' : '-' }}
                </div>
              </CardContent>
            </Card>
            
            <Card>
              <CardContent class="pt-4">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 text-sm mb-1">
                  <Droplets class="w-4 h-4" />
                  <span>Rata-rata Kelembaban</span>
                </div>
                <div class="text-2xl font-bold" :class="getHumidityColor(selectedDay.avg_humidity)">
                  {{ selectedDay.avg_humidity !== null ? selectedDay.avg_humidity.toFixed(1) + '%' : '-' }}
                </div>
              </CardContent>
            </Card>
          </div>
          
          <!-- Morning & Evening Readings -->
          <div class="space-y-3">
            <!-- Morning -->
            <Card>
              <CardHeader class="pb-3">
                <CardTitle class="text-sm flex items-center gap-2">
                  <Sunrise class="w-4 h-4 text-orange-500" />
                  Data Pagi
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div v-if="selectedDay.morning_reading" class="grid grid-cols-3 gap-3 text-sm">
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Waktu</div>
                    <div class="font-semibold">{{ selectedDay.morning_reading.time }}</div>
                  </div>
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Suhu</div>
                    <div class="font-semibold" :class="getTempColor(selectedDay.morning_reading.temperature_c)">
                      {{ selectedDay.morning_reading.temperature_c.toFixed(1) }}°C
                    </div>
                  </div>
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Kelembaban</div>
                    <div class="font-semibold" :class="getHumidityColor(selectedDay.morning_reading.humidity)">
                      {{ selectedDay.morning_reading.humidity.toFixed(1) }}%
                    </div>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400">Tidak ada data</div>
              </CardContent>
            </Card>
            
            <!-- Evening -->
            <Card>
              <CardHeader class="pb-3">
                <CardTitle class="text-sm flex items-center gap-2">
                  <Sunset class="w-4 h-4 text-purple-500" />
                  Data Sore
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div v-if="selectedDay.evening_reading" class="grid grid-cols-3 gap-3 text-sm">
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Waktu</div>
                    <div class="font-semibold">{{ selectedDay.evening_reading.time }}</div>
                  </div>
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Suhu</div>
                    <div class="font-semibold" :class="getTempColor(selectedDay.evening_reading.temperature_c)">
                      {{ selectedDay.evening_reading.temperature_c.toFixed(1) }}°C
                    </div>
                  </div>
                  <div>
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Kelembaban</div>
                    <div class="font-semibold" :class="getHumidityColor(selectedDay.evening_reading.humidity)">
                      {{ selectedDay.evening_reading.humidity.toFixed(1) }}%
                    </div>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-400">Tidak ada data</div>
              </CardContent>
            </Card>
          </div>
          
          <!-- Total Readings -->
          <div class="text-sm text-gray-600 dark:text-gray-400 text-center">
            Total Data: <span class="font-semibold">{{ selectedDay.readings_count }}</span>
          </div>
        </div>
        
        <DialogFooter>
          <Button variant="outline" @click="selectedDay = null">Tutup</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    
    <!-- Record Detail Modal (Max/Min Temperature) -->
    <Dialog :open="selectedRecord !== null" @update:open="(v: boolean) => { if (!v) selectedRecord = null }">
      <DialogContent class="sm:max-w-[450px]">
        <DialogHeader>
          <DialogTitle v-if="selectedRecord">
            {{ selectedRecord.type }} Record
          </DialogTitle>
          <!-- <DialogDescription>
            Detail pembacaan sensor saat record terjadi
          </DialogDescription> -->
        </DialogHeader>
        
        <div v-if="selectedRecord" class="space-y-4">
          <!-- Record Info -->
          <div class="bg-gradient-to-br from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <Thermometer class="w-5 h-5" :class="selectedRecord.type.includes('Max') ? 'text-red-500' : 'text-blue-500'" />
                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ selectedRecord.type }}</span>
              </div>
              <div class="text-2xl font-bold" :class="selectedRecord.type.includes('Max') ? 'text-red-500' : 'text-blue-500'">
                {{ selectedRecord.record.temperature_c.toFixed(1) }}°C
              </div>
            </div>
            
            <!-- Date & Time -->
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <div class="text-gray-500 dark:text-gray-400 text-xs mb-1">Tanggal</div>
                <div class="font-semibold text-gray-700 dark:text-gray-300">
                  {{ formatRecordDate(selectedRecord.record.recorded_at).date }}
                </div>
              </div>
              <div>
                <div class="text-gray-500 dark:text-gray-400 text-xs mb-1">Waktu</div>
                <div class="font-semibold text-gray-700 dark:text-gray-300">
                  {{ formatRecordDate(selectedRecord.record.recorded_at).time }}
                </div>
              </div>
            </div>
          </div>
          
          <!-- Sensor Data -->
          <div class="grid grid-cols-2 gap-4">
            <Card>
              <CardContent class="pt-4">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 text-sm mb-1">
                  <Thermometer class="w-4 h-4" />
                  <span>Suhu</span>
                </div>
                <div class="text-2xl font-bold" :class="getTempColor(selectedRecord.record.temperature_c)">
                  {{ selectedRecord.record.temperature_c.toFixed(1) }}°C
                </div>
              </CardContent>
            </Card>
            
            <Card>
              <CardContent class="pt-4">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 text-sm mb-1">
                  <Droplets class="w-4 h-4" />
                  <span>Kelembaban</span>
                </div>
                <div class="text-2xl font-bold" :class="getHumidityColor(selectedRecord.record.humidity)">
                  {{ selectedRecord.record.humidity.toFixed(1) }}%
                </div>
              </CardContent>
            </Card>
          </div>
          
          <!-- Device Info -->
          <div class="text-sm text-gray-600 dark:text-gray-400 text-center bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            Device: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ selectedRecord.record.device_id }}</span>
          </div>
        </div>
        
        <DialogFooter>
          <Button variant="outline" @click="selectedRecord = null">Tutup</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
/* Minimal custom styles */
</style>

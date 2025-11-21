<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import RealtimeLineChart from '@/components/charts/RealtimeLineChart.vue';

interface Reading {
  device_id: string;
  temperature_c: number;
  humidity: number;
  recorded_at: string;
}

const breadcrumbs = [
  { title: 'Sensor Monitoring', href: '/sensor-monitoring' },
];

const deviceId = ref<string>('nodemcu-1');
const limit = ref<number>(100);
const readings = ref<Reading[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const lastUpdate = ref<string>('');

async function fetchReadings() {
  isLoading.value = true;
  error.value = null;
  try {
    const qs = new URLSearchParams();
    if (deviceId.value) qs.set('device_id', deviceId.value);
    qs.set('limit', String(limit.value));
    const res = await fetch(`/api/sensors/dht/recent?${qs.toString()}`);
    const json = await res.json();
    readings.value = (json?.data || []).reverse(); 
    lastUpdate.value = new Date().toLocaleString();
  } catch (e: any) {
    error.value = e?.message || 'Failed to load data';
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  fetchReadings();
});

const labels = computed(() => readings.value.map((r) => new Date(r.recorded_at).toLocaleTimeString()));
const tempSeries = computed(() => readings.value.map((r) => r.temperature_c));
const humidSeries = computed(() => readings.value.map((r) => r.humidity));
const datasets = computed(() => [
  { id: 'temp', label: 'Suhu (°C)', data: tempSeries.value, borderColor: '#ef4444' },
  { id: 'humid', label: 'Kelembaban (%)', data: humidSeries.value, borderColor: '#06b6d4' },
]);

</script>

<template>
  <Head title="Sensor Monitoring" />
  <AppHeaderLayout :breadcrumbs="breadcrumbs">
    <template #title>Sensor Monitoring</template>
    <template #description>Grafik dan data realtime untuk suhu & kelembaban.</template>

    <div class="grid gap-6">
      <Card class="shadow-sm">
        <CardHeader>
          <CardTitle>DHT22 Readings</CardTitle>
          <CardDescription>Device: {{ deviceId }} • Last update: {{ lastUpdate }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex items-center gap-3 mb-4">
            <input v-model="deviceId" placeholder="device_id" class="px-3 py-2 border rounded" />
            <select v-model.number="limit" class="px-3 py-2 border rounded">
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
            </select>
            <Button :disabled="isLoading" @click="fetchReadings">Refresh</Button>
            <span v-if="isLoading" class="text-sm text-gray-500">Loading...</span>
            <span v-if="error" class="text-sm text-red-600">{{ error }}</span>
          </div>

          <RealtimeLineChart :labels="labels" :datasets="datasets" :height="280" :dark="false" title="Suhu & Kelembaban" />
        </CardContent>
      </Card>

      <Card class="shadow-sm">
        <CardHeader>
          <CardTitle>Data Terbaru</CardTitle>
          <CardDescription>Tabel ringkas 20 data terakhir</CardDescription>
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
                <tr v-for="r in readings.slice(-20).reverse()" :key="`${r.device_id}-${r.recorded_at}`" class="border-b">
                  <td class="px-2 py-2">{{ new Date(r.recorded_at).toLocaleString() }}</td>
                  <td class="px-2 py-2">{{ r.device_id }}</td>
                  <td class="px-2 py-2">{{ r.temperature_c?.toFixed(2) }}</td>
                  <td class="px-2 py-2">{{ r.humidity?.toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppHeaderLayout>
</template>
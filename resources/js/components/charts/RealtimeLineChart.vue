<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

interface LineDataset {
  id?: string;
  label: string;
  data: Array<number | null>;
  borderColor?: string;
  backgroundColor?: string;
  pointRadius?: number;
  tension?: number;
}

interface Props {
  labels: string[];
  values?: number[]; // fallback single-series
  datasets?: LineDataset[]; // multi-series support
  title?: string;
  dark?: boolean;
  height?: number;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Average Response Time (ms)',
  dark: false,
  height: 240,
});

// Simple color palette for multiple series
const palette = ['#2563eb', '#16a34a', '#ef4444', '#f59e0b', '#9333ea', '#06b6d4', '#10b981', '#8b5cf6', '#3b82f6', '#14b8a6', '#f97316', '#dc2626'];

// Build chart data using non-reactive copies to avoid mutation loops
const chartData = computed(() => {
  const safeLabels = Array.isArray(props.labels) ? [...props.labels] : [];

  // If multi-series provided, use them; otherwise fallback to single series
  let datasets: any[] = [];

  if (Array.isArray(props.datasets) && props.datasets.length > 0) {
    datasets = props.datasets.map((ds, idx) => {
      const color = ds.borderColor || palette[idx % palette.length];
      const data = Array.isArray(ds.data) ? ds.data.map((v) => (v === null || (typeof v === 'number' && Number.isFinite(v)) ? v : null)) : [];
      return {
        label: ds.label || `Series ${idx + 1}`,
        data,
        borderColor: color,
        backgroundColor: ds.backgroundColor || (props.dark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)'),
        pointRadius: ds.pointRadius ?? 2,
        tension: ds.tension ?? 0.25,
      };
    });
  } else {
    const safeValues = Array.isArray(props.values)
      ? props.values.map((v) => (typeof v === 'number' && Number.isFinite(v) ? v : null))
      : [];
    datasets = [
      {
        label: props.title || 'Average Response Time (ms)',
        data: safeValues,
        borderColor: props.dark ? '#60a5fa' : '#2563eb',
        backgroundColor: props.dark ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)',
        pointRadius: 2,
        tension: 0.25,
      },
    ];
  }

  return {
    labels: safeLabels,
    datasets,
  };
});

const gridColor = computed(() => (props.dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)'));
const tickColor = computed(() => (props.dark ? '#cbd5e1' : '#525252'));

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 300 },
  plugins: {
    legend: { 
      display: true,
      position: 'bottom',
      labels: {
        color: tickColor.value,
        usePointStyle: true,
      },
    },
    tooltip: {
      enabled: true,
      mode: 'nearest',
      intersect: false,
      callbacks: {
        label: (ctx: any) => {
          const label = ctx.dataset?.label ?? 'Series';
          const value = ctx.parsed?.y;
          return `${label}: ${value} ms`;
        },
      },
    },
  },
  interaction: { mode: 'nearest', intersect: false },
  scales: {
    x: {
      grid: { color: gridColor.value },
      ticks: { color: tickColor.value, maxRotation: 0 },
    },
    y: {
      beginAtZero: true,
      grid: { color: gridColor.value },
      ticks: { color: tickColor.value },
    },
  },
}));

// We avoid watching reactive arrays directly to prevent infinite update loops
</script>

<template>
  <div class="w-full" :style="{ height: `${height}px` }">
    <Line :data="chartData" :options="chartOptions" />
  </div>
  
</template>
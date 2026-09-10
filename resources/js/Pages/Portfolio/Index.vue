<template>
  <div>
    <Head title="Portfolio" />
    <h1 class="mb-2 text-3xl font-bold">Portfolio</h1>
    <p class="mb-6 text-sm text-gray-600">
      Binance Est. Total Value over time (snapshots every 5 minutes).
    </p>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Latest Est. Total</div>
        <div class="mt-1 text-3xl font-semibold text-gray-900">
          <template v-if="summary.latest != null">
            {{ formatMoney(summary.latest) }}
            <span class="text-lg font-medium text-gray-500">USDT</span>
          </template>
          <template v-else>—</template>
        </div>
        <p class="mt-1 text-sm" :class="deltaClass(summary.delta)">
          <template v-if="summary.delta != null">
            {{ formatDelta(summary.delta) }}
            ({{ formatPct(summary.delta_pct) }}) over range
          </template>
          <template v-else>Need at least two snapshots in this range.</template>
        </p>
        <p class="mt-1 text-xs text-gray-400">
          {{ summary.points }} points
          <span v-if="summary.usdt_cash != null">· USDT cash {{ formatMoney(summary.usdt_cash) }}</span>
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-for="opt in ranges"
          :key="opt.value"
          type="button"
          class="rounded px-3 py-1.5 text-sm font-medium"
          :class="range === opt.value ? 'bg-slate-800 text-white' : 'bg-white text-gray-700 shadow border border-gray-200'"
          @click="setRange(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
      <svg
        v-if="chart.points.length >= 2"
        class="w-full"
        :viewBox="`0 0 ${chart.width} ${chart.height}`"
        preserveAspectRatio="none"
        role="img"
        aria-label="Est. Total Value chart"
      >
        <polyline
          fill="none"
          stroke="#0f172a"
          stroke-width="2"
          vector-effect="non-scaling-stroke"
          :points="chart.polyline"
        />
        <line
          v-if="chart.zeroY != null"
          :x1="0"
          :x2="chart.width"
          :y1="chart.zeroY"
          :y2="chart.zeroY"
          stroke="#e5e7eb"
          stroke-width="1"
          stroke-dasharray="4 4"
        />
      </svg>
      <p v-else class="py-16 text-center text-sm text-gray-500">
        Collecting snapshots… refresh in a few minutes after the scheduler runs
        (<code class="text-xs">wallet:snapshot-equity</code>).
      </p>

      <div v-if="chart.points.length >= 2" class="mt-3 flex justify-between text-xs text-gray-500">
        <span>{{ formatWhen(series[0]?.t) }}</span>
        <span>min {{ formatMoney(chart.min) }} · max {{ formatMoney(chart.max) }}</span>
        <span>{{ formatWhen(series[series.length - 1]?.t) }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import { DateTime } from 'luxon'
import { Head } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'

export default {
  components: { Head },
  layout: Layout,
  props: {
    range: { type: String, default: '7d' },
    summary: Object,
    series: { type: Array, default: () => [] },
  },
  data() {
    return {
      ranges: [
        { value: '24h', label: '24h' },
        { value: '7d', label: '7d' },
        { value: '30d', label: '30d' },
      ],
    }
  },
  computed: {
    chart() {
      const width = 800
      const height = 280
      const padY = 16
      const values = this.series.map((p) => Number(p.v)).filter((v) => !Number.isNaN(v))
      if (values.length < 2) {
        return { width, height, points: [], polyline: '', min: null, max: null, zeroY: null }
      }

      const min = Math.min(...values)
      const max = Math.max(...values)
      const span = max - min || 1

      const pts = values.map((v, i) => {
        const x = (i / (values.length - 1)) * width
        const y = padY + (1 - (v - min) / span) * (height - padY * 2)
        return { x, y, v }
      })

      return {
        width,
        height,
        points: pts,
        polyline: pts.map((p) => `${p.x.toFixed(2)},${p.y.toFixed(2)}`).join(' '),
        min,
        max,
        zeroY: null,
      }
    },
  },
  methods: {
    setRange(value) {
      this.$inertia.get('/portfolio', { range: value }, { preserveState: true, replace: true })
    },
    formatWhen(iso) {
      if (!iso) return '—'
      return DateTime.fromISO(iso).toLocal().toFormat('MM/dd h:mma')
    },
    formatMoney(v) {
      if (v === null || v === undefined) return '—'
      return Number(v).toFixed(4)
    },
    formatDelta(v) {
      if (v === null || v === undefined) return '—'
      const n = Number(v)
      return `${n >= 0 ? '+' : ''}${n.toFixed(4)}`
    },
    formatPct(v) {
      if (v === null || v === undefined) return '—'
      return `${Number(v).toFixed(3)}%`
    },
    deltaClass(v) {
      if (v === null || v === undefined) return 'text-gray-500'
      if (v > 0) return 'text-green-600'
      if (v < 0) return 'text-red-600'
      return 'text-gray-700'
    },
  },
}
</script>

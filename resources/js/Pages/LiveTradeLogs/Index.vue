<template>
  <div>
    <Head title="Live Trades" />
    <h1 class="mb-8 text-3xl font-bold">Live Trades (USDT)</h1>

    <p class="mb-2 text-sm text-gray-600">
      {{ summary.total }} trades ·
      {{ summary.completed }} completed ·
      {{ summary.partial }} partial
    </p>
    <p class="mb-6 text-sm text-gray-600">
      Equity net
      <span :class="deltaClass(summary.net_equity)">{{ formatDelta(summary.net_equity) }}</span>
      · mean
      <span :class="deltaClass(summary.mean_equity_delta_pct)">{{ formatPct(summary.mean_equity_delta_pct) }}</span>
      vs capital
      <span class="text-gray-400">
        (USDT-only net
        <span :class="deltaClass(summary.net_usdt)">{{ formatDelta(summary.net_usdt) }}</span>)
      </span>
    </p>

    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <label class="text-sm text-gray-700">Status</label>
        <select v-model="form.status" class="form-select">
          <option :value="null">All</option>
          <option value="completed">Completed</option>
          <option value="partial">Partial</option>
          <option value="failed">Failed</option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap text-sm">
        <thead>
          <tr class="text-left font-bold">
            <th class="pb-3 pt-4 px-6">When</th>
            <th class="pb-3 pt-4 px-6">Path</th>
            <th class="pb-3 pt-4 px-6">Dir</th>
            <th class="pb-3 pt-4 px-6">Capital</th>
            <th class="pb-3 pt-4 px-6">Equity before</th>
            <th class="pb-3 pt-4 px-6">Equity after</th>
            <th class="pb-3 pt-4 px-6">Δ Equity</th>
            <th class="pb-3 pt-4 px-6">Δ Eq %</th>
            <th class="pb-3 pt-4 px-6">Δ USDT</th>
            <th class="pb-3 pt-4 px-6">Sim %</th>
            <th class="pb-3 pt-4 px-6">Status</th>
            <th class="pb-3 pt-4 px-6">Source</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in logs.data" :key="row.id" class="hover:bg-gray-50">
            <td class="border-t px-6 py-3">{{ formatWhen(row.created_at) }}</td>
            <td class="border-t px-6 py-3">{{ row.path }}</td>
            <td class="border-t px-6 py-3">{{ row.direction || '—' }}</td>
            <td class="border-t px-6 py-3">{{ formatMoney(row.capital) }}</td>
            <td class="border-t px-6 py-3">{{ formatMoney(row.equity_before) }}</td>
            <td class="border-t px-6 py-3">{{ formatMoney(row.equity_after) }}</td>
            <td class="border-t px-6 py-3" :class="deltaClass(row.equity_delta)">{{ formatDelta(row.equity_delta) }}</td>
            <td class="border-t px-6 py-3" :class="deltaClass(row.equity_delta_pct)">{{ formatPct(row.equity_delta_pct) }}</td>
            <td class="border-t px-6 py-3" :class="deltaClass(row.usdt_delta)">{{ formatDelta(row.usdt_delta) }}</td>
            <td class="border-t px-6 py-3">{{ formatPct(row.sim_profit_pct) }}</td>
            <td class="border-t px-6 py-3">
              <span
                class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                :class="statusClass(row.status)"
                :title="row.error || ''"
              >
                {{ row.status }}
              </span>
            </td>
            <td class="border-t px-6 py-3">{{ row.source }}</td>
          </tr>
          <tr v-if="logs.data.length === 0">
            <td class="px-6 py-4 border-t" colspan="12">No live trades yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <pagination class="mt-6" :links="logs.links" />
  </div>
</template>

<script>
import { DateTime } from 'luxon'
import { Head } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import Pagination from '@/Shared/Pagination.vue'
import throttle from 'lodash/throttle'
import pickBy from 'lodash/pickBy'

export default {
  components: { Head, Pagination },
  layout: Layout,
  props: {
    summary: Object,
    logs: Object,
    filters: Object,
  },
  data() {
    return {
      form: {
        status: this.filters.status || null,
      },
    }
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        this.$inertia.get('/live-trades', pickBy(this.form), { preserveState: true })
      }, 150),
    },
  },
  methods: {
    formatWhen(iso) {
      if (!iso) return '—'
      return DateTime.fromISO(iso).toLocal().toFormat('MM/dd/yyyy h:mma')
    },
    formatMoney(v) {
      if (v === null || v === undefined) return '—'
      return Number(v).toFixed(4)
    },
    formatDelta(v) {
      if (v === null || v === undefined) return '—'
      const n = Number(v)
      return `${n >= 0 ? '+' : ''}${n.toFixed(6)}`
    },
    formatPct(v) {
      if (v === null || v === undefined) return '—'
      return `${Number(v).toFixed(4)}%`
    },
    deltaClass(v) {
      if (v === null || v === undefined) return 'text-gray-400'
      if (v > 0) return 'text-green-600'
      if (v < 0) return 'text-red-600'
      return 'text-gray-700'
    },
    statusClass(status) {
      if (status === 'completed') return 'bg-emerald-100 text-emerald-800'
      if (status === 'partial') return 'bg-amber-100 text-amber-800'
      return 'bg-red-100 text-red-800'
    },
  },
}
</script>

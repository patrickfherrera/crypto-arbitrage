<template>
  <div>
    <Head title="Arbitrage Logs" />
    <h1 class="mb-8 text-3xl font-bold">Arbitrage Logs</h1>

    <p class="mb-4 text-sm text-gray-600">
      {{ summary.total }} rows
      · best
      <span :class="profitPctClass(summary.best_pct)">{{ formatProfitPct(summary.best_pct) }}</span>
      · mean
      <span :class="profitPctClass(summary.mean_pct)">{{ formatProfitPct(summary.mean_pct) }}</span>
      <span class="text-gray-400"> · refresh in {{ refreshIn }}s</span>
    </p>

    <p v-if="form.coin_arbitrage_id" class="mb-4 text-sm text-gray-700">
      Filtered by path:
      <span class="font-medium">{{ selectedPathLabel }}</span>
      <button type="button" class="ml-2 text-slate-600 underline hover:text-slate-900" @click="clearPathFilter">
        Clear
      </button>
    </p>

    <div class="flex items-center justify-between mb-6">
      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" :max-width="360" @reset="reset">
        <label class="block text-gray-700">Profitable:</label>
        <select v-model="form.profitable" class="form-select mt-1 w-full">
          <option :value="null">All</option>
          <option value="PROFITABLE">Profitable</option>
          <option value="NOT_PROFITABLE">Non Profitable</option>
        </select>

        <label class="block text-gray-700 mt-4">Direction:</label>
        <select v-model="form.direction" class="form-select mt-1 w-full">
          <option :value="null">All</option>
          <option value="forward">Forward</option>
          <option value="reverse">Reverse</option>
        </select>

        <label class="block text-gray-700 mt-4">Path:</label>
        <select v-model="form.coin_arbitrage_id" class="form-select mt-1 w-full">
          <option value="">All</option>
          <option v-for="arb in arbitrages" :key="arb.id" :value="String(arb.id)">
            {{ arb.label }}
          </option>
        </select>

        <label class="block text-gray-700 mt-4">By triangle sort:</label>
        <select v-model="form.triangle_sort" class="form-select mt-1 w-full">
          <option value="wins">Most wins</option>
          <option value="win_rate">Win rate</option>
          <option value="best_pct">Best %</option>
          <option value="mean_pct">Mean %</option>
          <option value="rows">Most rows</option>
        </select>

        <label class="block text-gray-700 mt-4">Logs sort:</label>
        <select v-model="form.sort" class="form-select mt-1 w-full">
          <option value="newest">Newest</option>
          <option value="oldest">Oldest</option>
          <option value="best_pct">Best profit %</option>
          <option value="worst_pct">Worst profit %</option>
        </select>
      </search-filter>
    </div>

    <h2 class="mb-2 text-lg font-semibold">By Triangle</h2>
    <div class="mb-2 bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap text-sm">
        <thead>
          <tr class="text-left font-bold">
            <th class="pb-3 pt-4 px-6">Path</th>
            <th class="pb-3 pt-4 px-6">Rows</th>
            <th class="pb-3 pt-4 px-6">Wins</th>
            <th class="pb-3 pt-4 px-6">Win rate</th>
            <th class="pb-3 pt-4 px-6">Best</th>
            <th class="pb-3 pt-4 px-6">Mean</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in byTriangle.data" :key="row.coin_arbitrage_id" class="hover:bg-gray-50">
            <td class="border-t px-6 py-3">{{ row.path }}</td>
            <td class="border-t px-6 py-3">{{ row.total }}</td>
            <td class="border-t px-6 py-3">{{ row.wins }}</td>
            <td class="border-t px-6 py-3">{{ row.win_rate.toFixed(2) }}%</td>
            <td class="border-t px-6 py-3" :class="profitPctClass(row.best_pct)">{{ formatProfitPct(row.best_pct) }}</td>
            <td class="border-t px-6 py-3" :class="profitPctClass(row.mean_pct)">{{ formatProfitPct(row.mean_pct) }}</td>
          </tr>
          <tr v-if="byTriangle.data.length === 0">
            <td class="px-6 py-4 border-t" colspan="6">No triangle stats yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <pagination class="mb-8" :links="byTriangle.links" />

    <h2 class="mb-2 text-lg font-semibold">Recent logs</h2>
    <div class="bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap">
        <tr class="text-left font-bold">
          <th class="pb-4 pt-6 px-6">Date</th>
          <th class="pb-4 pt-6 px-6">Path</th>
          <th class="pb-4 pt-6 px-6">Capital</th>
          <th class="pb-4 pt-6 px-6">Profit</th>
          <th class="pb-4 pt-6 px-6">Profit %</th>
          <th class="pb-4 pt-6 px-6">Direction</th>
          <th class="pb-4 pt-6 px-6">Quote Age (ms)</th>
          <th class="pb-4 pt-6 px-6">Status</th>
        </tr>
        <tr
          v-for="arbitrageLog in formattedLogs"
          :key="arbitrageLog.id"
          class="hover:bg-gray-100 focus-within:bg-gray-100"
        >
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.created_at }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.path }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.capital }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.profit }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4" :class="profitPctClass(arbitrageLog.profit_pct)">
              {{ formatProfitPct(arbitrageLog.profit_pct) }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.direction || '—' }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.quote_age_ms ?? '—' }}</div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4">{{ arbitrageLog.status }}</div>
          </td>
        </tr>
        <tr v-if="arbitrageLogs.data.length === 0">
          <td class="px-6 py-4 border-t" colspan="8">No arbitrage logs found.</td>
        </tr>
      </table>
    </div>
    <pagination class="mt-6" :links="arbitrageLogs.links" />
  </div>
</template>

<script>
import { DateTime } from 'luxon'
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import pickBy from 'lodash/pickBy'
import Layout from '@/Shared/Layout.vue'
import throttle from 'lodash/throttle'
import Pagination from '@/Shared/Pagination.vue'
import SearchFilter from '@/Shared/SearchFilter.vue'

export default {
  components: {
    Head,
    Icon,
    Link,
    Pagination,
    SearchFilter,
  },
  layout: Layout,
  props: {
    filters: Object,
    arbitrages: Array,
    summary: Object,
    byTriangle: { type: Object, default: () => ({ data: [], links: [] }) },
    arbitrageLogs: Object,
  },
  data() {
    return {
      refreshIntervalMs: 15000,
      refreshIn: 15,
      refreshTimer: null,
      form: {
        search: this.filters.search,
        profitable: this.filters.profitable,
        direction: this.filters.direction,
        coin_arbitrage_id: this.filters.coin_arbitrage_id ? String(this.filters.coin_arbitrage_id) : '',
        sort: this.filters.sort || 'newest',
        triangle_sort: this.filters.triangle_sort || 'wins',
      },
    }
  },
  computed: {
    formattedLogs() {
      return this.arbitrageLogs.data.map(arbitrageLog => ({
        ...arbitrageLog,
        created_at: DateTime.fromISO(arbitrageLog.created_at).toLocal().toFormat('MM/dd/yyyy h:mma'),
      }))
    },
    selectedPathLabel() {
      const id = String(this.form.coin_arbitrage_id || '')
      const match = (this.arbitrages || []).find(arb => String(arb.id) === id)
      return match?.label || `#${id}`
    },
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        const query = pickBy(this.form, (value, key) => {
          if (value === null || value === undefined || value === '') return false
          if (key === 'sort' && value === 'newest') return false
          if (key === 'triangle_sort' && value === 'wins') return false
          return true
        })
        this.$inertia.get('/arbitrage-logs', query, { preserveState: true })
      }, 150),
    },
  },
  mounted() {
    this.refreshIn = this.refreshIntervalMs / 1000
    this.refreshTimer = setInterval(() => {
      this.refreshIn -= 1
      if (this.refreshIn <= 0) {
        this.refreshIn = this.refreshIntervalMs / 1000
        this.$inertia.reload({
          only: ['arbitrageLogs', 'summary', 'byTriangle'],
          preserveState: true,
          preserveScroll: true,
        })
      }
    }, 1000)
  },
  beforeUnmount() {
    if (this.refreshTimer) clearInterval(this.refreshTimer)
  },
  methods: {
    clearPathFilter() {
      this.$inertia.get('/arbitrage-logs')
    },
    reset() {
      this.form = {
        search: null,
        profitable: null,
        direction: null,
        coin_arbitrage_id: '',
        sort: 'newest',
        triangle_sort: 'wins',
      }
    },
    formatProfitPct(value) {
      if (value === null || value === undefined) return '—'
      return `${Number(value).toFixed(4)}%`
    },
    profitPctClass(value) {
      if (value === null || value === undefined) return 'text-gray-400'
      if (value > 0) return 'text-green-600'
      if (value < 0) return 'text-red-600'
      return 'text-gray-700'
    },
  },
}
</script>

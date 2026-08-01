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
      <span class="text-gray-400"> · auto-refresh 15s</span>
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
          <option :value="null">All</option>
          <option v-for="arb in arbitrages" :key="arb.id" :value="String(arb.id)">
            {{ arb.label }}
          </option>
        </select>

        <label class="block text-gray-700 mt-4">Sort:</label>
        <select v-model="form.sort" class="form-select mt-1 w-full">
          <option value="newest">Newest</option>
          <option value="best_pct">Best profit %</option>
        </select>
      </search-filter>
    </div>

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
    arbitrageLogs: Object,
  },
  data() {
    return {
      refreshTimer: null,
      form: {
        search: this.filters.search,
        profitable: this.filters.profitable,
        direction: this.filters.direction,
        coin_arbitrage_id: this.filters.coin_arbitrage_id,
        sort: this.filters.sort || 'newest',
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
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        this.$inertia.get('/arbitrage-logs', pickBy(this.form), { preserveState: true })
      }, 150),
    },
  },
  mounted() {
    this.refreshTimer = setInterval(() => {
      this.$inertia.reload({
        only: ['arbitrageLogs', 'summary'],
        preserveState: true,
        preserveScroll: true,
      })
    }, 15000)
  },
  beforeUnmount() {
    if (this.refreshTimer) clearInterval(this.refreshTimer)
  },
  methods: {
    reset() {
      this.form = {
        search: null,
        profitable: null,
        direction: null,
        coin_arbitrage_id: null,
        sort: 'newest',
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
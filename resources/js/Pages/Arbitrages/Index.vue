<template>
  <div>
    <Head title="Arbitrage" />
    <h1 class="mb-8 text-3xl font-bold">Arbitrage</h1>
    <div class="flex items-center justify-between mb-6">
      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" @reset="reset">
        <label class="block text-gray-700">Enabled:</label>
        <select v-model="form.enabled" class="form-select mt-1 w-full">
          <option value="all">All</option>
          <option value="enabled">Enabled</option>
          <option value="disabled">Disabled</option>
        </select>
      </search-filter>
      <Link class="btn-primary" href="/arbitrages/create">
        <span>Create</span>
        <span class="hidden md:inline">&nbsp;Arbitrage</span>
      </Link>
    </div>
    <div class="bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap">
        <thead>
        <tr class="text-left font-bold">
          <th class="pb-4 pt-6 px-6">Enabled</th>
          <th class="pb-4 pt-6 px-6">Test</th>
          <th class="pb-4 pt-6 px-6">Capital</th>
          <th class="pb-4 pt-6 px-6">Created</th>
          <th class="pb-4 pt-6 px-6">Coin One</th>
          <th class="pb-4 pt-6 px-6"></th>
          <th class="pb-4 pt-6 px-6">Coin Two</th>
          <th class="pb-4 pt-6 px-6"></th>
          <th class="pb-4 pt-6 px-6">Coin Three</th>
          <th class="pb-4 pt-6 px-6"></th>
          <th class="pb-4 pt-6 px-6">Rows</th>
          <th class="pb-4 pt-6 px-6">Wins</th>
          <th class="pb-4 pt-6 px-6">Win rate</th>
          <th class="pb-4 pt-6 px-6">Best</th>
          <th class="pb-4 pt-6 px-6">Mean</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="arbitrage in arbitrages.data" :key="arbitrage.id" class="hover:bg-gray-100 focus-within:bg-gray-100">
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              <span
                class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                :class="arbitrage.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
              >
                {{ arbitrage.enabled ? 'On' : 'Off' }}
              </span>
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              <span
                class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                :class="arbitrage.test_mode ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800'"
              >
                {{ arbitrage.test_mode ? 'Test' : 'Live' }}
              </span>
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ formatCapital(arbitrage.capital) }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ formatCreatedAt(arbitrage.created_at) }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.coin_one.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ directionLabel(arbitrage.coin_one, arbitrage.coin_one_price) }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.coin_two.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ directionLabel(arbitrage.coin_two, arbitrage.coin_two_price) }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.coin_three.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ directionLabel(arbitrage.coin_three, arbitrage.coin_three_price) }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.log_total }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.wins }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ formatWinRate(arbitrage.win_rate) }}
            </Link>
          </td>
          <td class="border-t">
            <Link
              class="flex items-center px-6 py-4"
              :href="`/arbitrages/${arbitrage.id}/edit`"
              tabindex="-1"
              :class="profitPctClass(arbitrage.best_pct)"
            >
              {{ formatProfitPct(arbitrage.best_pct) }}
            </Link>
          </td>
          <td class="border-t">
            <Link
              class="flex items-center px-6 py-4"
              :href="`/arbitrages/${arbitrage.id}/edit`"
              tabindex="-1"
              :class="profitPctClass(arbitrage.mean_pct)"
            >
              {{ formatProfitPct(arbitrage.mean_pct) }}
            </Link>
          </td>
          <td class="w-px border-t">
            <Link class="flex items-center px-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400" />
            </Link>
          </td>
        </tr>
        <tr v-if="arbitrages.data.length === 0">
          <td class="px-6 py-4 border-t" colspan="16">No arbitrages found.</td>
        </tr>
        </tbody>
      </table>
    </div>
    <pagination class="mt-6" :links="arbitrages.links" />
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
    arbitrages: Object,
  },
  data() {
    return {
      form: {
        search: this.filters.search,
        enabled: this.filters.enabled || 'all',
      },
    }
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        this.$inertia.get('/arbitrages', pickBy(this.form), { preserveState: true })
      }, 150),
    },
  },
  methods: {
    directionLabel(coin, priceSide) {
      // ask = buy base with quote; bid = sell base for quote
      if (priceSide === 'askPrice') {
        return `${coin.quote_asset} → ${coin.base_asset}`
      }
      return `${coin.base_asset} → ${coin.quote_asset}`
    },
    formatCreatedAt(value) {
      if (!value) return '—'
      return DateTime.fromISO(value).toLocal().toFormat('MM/dd/yyyy h:mma')
    },
    formatCapital(value) {
      if (value === null || value === undefined) return '—'
      return Number(value).toFixed(2)
    },
    formatWinRate(value) {
      if (value === null || value === undefined) return '—'
      return `${Number(value).toFixed(2)}%`
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
    reset() {
      this.form = {
        search: null,
        enabled: 'all',
      }
    },
  },
}
</script>

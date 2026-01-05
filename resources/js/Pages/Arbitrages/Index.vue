<template>
  <div>
    <Head title="Arbitrage" />
    <h1 class="mb-8 text-3xl font-bold">Arbitrage</h1>
    <div class="flex items-center justify-between mb-6">
      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" @reset="reset">
        <label class="block text-gray-700">Trashed:</label>
        <select v-model="form.trashed" class="form-select mt-1 w-full">
          <option :value="null" />
          <option value="with">With Trashed</option>
          <option value="only">Only Trashed</option>
        </select>
      </search-filter>
      <Link class="btn-indigo" href="/arbitrage/create">
        <span>Create</span>
        <span class="hidden md:inline">&nbsp;Arbitrage</span>
      </Link>
    </div>
    <div class="bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap">
        <thead>
        <tr class="text-left font-bold">
          <th class="pb-4 pt-6 px-6">Coin One</th>
          <th class="pb-4 pt-6 px-6"></th>
          <th class="pb-4 pt-6 px-6">Coin Two</th>
          <th class="pb-4 pt-6 px-6"></th>
          <th class="pb-4 pt-6 px-6">Coin Three</th>
          <th class="pb-4 pt-6 px-6"></th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="arbitrage in arbitrages.data" :key="arbitrage.id" class="hover:bg-gray-100 focus-within:bg-gray-100">
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.id }} {{ arbitrage.coin_one.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{
                (arbitrage.coin_one_price) === 'askPrice'
                  ? arbitrage.coin_one.base_asset + ' -> ' + arbitrage.coin_one.quote_asset
                  : arbitrage.coin_one.quote_asset + ' -> ' + arbitrage.coin_one.base_asset
              }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.coin_two.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{
                (arbitrage.coin_two_price) === 'bidPrice'
                  ? arbitrage.coin_two.quote_asset + ' -> ' + arbitrage.coin_two.base_asset
                  : arbitrage.coin_two.base_asset + ' -> ' + arbitrage.coin_two.quote_asset
              }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{ arbitrage.coin_three.symbol }}
            </Link>
          </td>
          <td class="border-t">
            <Link class="flex items-center px-6 py-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              {{
                (arbitrage.coin_three_price) === 'bidPrice'
                  ? arbitrage.coin_three.quote_asset + ' -> ' + arbitrage.coin_three.base_asset
                  : arbitrage.coin_three.base_asset + ' -> ' + arbitrage.coin_three.quote_asset
              }}
            </Link>
          </td>
          <td class="w-px border-t">
            <Link class="flex items-center px-4" :href="`/arbitrages/${arbitrage.id}/edit`" tabindex="-1">
              <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400" />
            </Link>
          </td>
        </tr>
        <tr v-if="arbitrages.data.length === 0">
          <td class="px-6 py-4 border-t" colspan="4">No arbitrages found.</td>
        </tr>
        </tbody>
      </table>
    </div>
    <pagination class="mt-6" :links="arbitrages.links" />
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import pickBy from 'lodash/pickBy'
import Layout from '@/Shared/Layout.vue'
import throttle from 'lodash/throttle'
import mapValues from 'lodash/mapValues'
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
        trashed: this.filters.trashed,
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
    reset() {
      this.form = mapValues(this.form, () => null)
    },
  },
}
</script>

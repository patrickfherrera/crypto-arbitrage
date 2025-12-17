<template>
  <div>
    <Head title="Arbitrage Logs" />
    <h1 class="mb-8 text-3xl font-bold">Arbitrage Logs</h1>
    <div class="flex items-center justify-between mb-6">
      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" @reset="reset">
        <label class="block text-gray-700">Profitable:</label>
        <select v-model="form.profitable" class="form-select mt-1 w-full">
          <option :value="null" />
          <option value="PROFITABLE">Profitable</option>
          <option value="NOT_PROFITABLE">Non Profitable</option>
        </select>
      </search-filter>
    </div>
    <div class="bg-white rounded-md shadow overflow-x-auto">
      <table class="w-full whitespace-nowrap">
        <tr class="text-left font-bold">
          <th class="pb-4 pt-6 px-6">Date</th>
          <th class="pb-4 pt-6 px-6">Coin One</th>
          <th class="pb-4 pt-6 px-6">Coin Two</th>
          <th class="pb-4 pt-6 px-6">Coin Three</th>
          <th class="pb-4 pt-6 px-6">Capital</th>
          <th class="pb-4 pt-6 px-6">Profit</th>
          <th class="pb-4 pt-6 px-6">Final Amount</th>
          <th class="pb-4 pt-6 px-6">Status</th>
        </tr>
        <tr v-for="arbitrageLog in arbitrageLogs.data" :key="arbitrageLog.id" class="hover:bg-gray-100 focus-within:bg-gray-100">
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.created_at }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.coin_one_name }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.coin_two_name }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.coin_three_name }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.capital }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.profit }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.final_amount }}
            </div>
          </td>
          <td class="border-t">
            <div class="flex items-center px-6 py-4 focus:text-indigo-500">
              {{ arbitrageLog.status }}
            </div>
          </td>
        </tr>
        <tr v-if="arbitrageLogs.data.length === 0">
          <td class="px-6 py-4 border-t" colspan="4">No arbitrage logs found.</td>
        </tr>
      </table>
    </div>
    <pagination class="mt-6" :links="arbitrageLogs.links" />
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
    arbitrageLogs: Object,
  },
  data() {
    return {
      form: {
        search: this.filters.search,
        profitable: this.filters.profitable,
      },
    }
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        this.$inertia.get('/arbitrage-logs', pickBy(this.form), { preserveState: true })
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

<template>
  <div>
    <Head title="Create Coins" />
    <h1 class="mb-8 text-3xl font-bold">
      <Link class="text-indigo-400 hover:text-indigo-600" href="/coins">Arbitrage</Link>
      <span class="text-indigo-400 font-medium">/</span> Create
    </h1>
    <div class="max-w-3xl bg-white rounded-md shadow overflow-hidden">
      <form @submit.prevent="store">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <h2 class="text-2xl font-bold">Coin One</h2>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.coin_one_id" :error="form.errors.coin_one_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Symbol">
            <option :value="null" />
            <option v-for="coin in coins" :key="coin.id" :value="coin.id">{{ coin.symbol }}</option>
          </select-input>
          <select-input v-model="form.coin_one_price" :error="form.errors.coin_one_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price">
            <option value="askPrice">askPrice</option>
            <option value="bidPrice">bidPrice</option>
          </select-input>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <h2 class="text-2xl font-bold">Coin Two</h2>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.coin_two_id" :error="form.errors.coin_two_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Symbol">
            <option :value="null" />
            <option v-for="coin in coins" :key="coin.id" :value="coin.id">{{ coin.symbol }}</option>
          </select-input>
          <select-input v-model="form.coin_two_price" :error="form.errors.coin_two_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price">
            <option value="askPrice">askPrice</option>
            <option value="bidPrice">bidPrice</option>
          </select-input>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <h2 class="text-2xl font-bold">Coin Three</h2>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.coin_three_id" :error="form.errors.coin_three_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Symbol">
            <option :value="null" />
            <option v-for="coin in coins" :key="coin.id" :value="coin.id">{{ coin.symbol }}</option>
          </select-input>
          <select-input v-model="form.coin_three_price" :error="form.errors.coin_three_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price">
            <option value="askPrice">askPrice</option>
            <option value="bidPrice">bidPrice</option>
          </select-input>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.profit" :error="form.errors.profit" class="pb-8 pr-6 w-full lg:w-1/2" label="Profit" />
          <text-input v-model="form.capital" :error="form.errors.capital" class="pb-8 pr-6 w-full lg:w-1/2" label="Capital" />

          <select-input v-model="form.test_mode" :error="form.errors.test_mode" class="pb-8 pr-6 w-full lg:w-1/2" label="Test Mode">
            <option value="0">No</option>
            <option value="1">Yes</option>
          </select-input>

          <select-input v-model="form.enabled" :error="form.errors.enabled" class="pb-8 pr-6 w-full lg:w-1/2" label="Enabled">
            <option value="0">No</option>
            <option value="1">Yes</option>
          </select-input>
        </div>
        <div class="flex items-center justify-end px-8 py-4 bg-gray-50 border-t border-gray-100">
          <loading-button :loading="form.processing" class="btn-indigo" type="submit">Create Arbitrage</loading-button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: {
    Head,
    Link,
    LoadingButton,
    SelectInput,
    TextInput,
  },
  layout: Layout,
  props: {
    coins: Array,
  },
  remember: 'form',
  data() {
    return {
      form: this.$inertia.form({
        coin_one_id: null,
        coin_one_price: null,
        coin_two_id: null,
        coin_two_price: null,
        coin_three_id: null,
        coin_three_price: null,
        profit: null,
        capital: null,
        test_mode: 1,
        enabled: 0,
      }),
    }
  },
  methods: {
    store() {
      this.form.post('/arbitrages')
    },
  },
}
</script>

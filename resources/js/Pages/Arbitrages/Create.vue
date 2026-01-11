<template>
  <div>
    <Head title="Create Coins" />
    <h1 class="mb-8 text-3xl font-bold">
      <Link class="text-indigo-400 hover:text-indigo-600" href="/coins">Arbitrage TEST</Link>
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
          <text-input v-model="form.coin_one_price" :error="form.errors.coin_one_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price" />
          <text-input v-model="form.coin_one_from_asset" :error="form.errors.coin_one_from_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="From Asset" />
          <text-input v-model="form.coin_one_to_asset" :error="form.errors.coin_one_to_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="To Asset" />
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <h2 class="text-2xl font-bold">Coin Two</h2>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.coin_two_id" :error="form.errors.coin_two_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Symbol">
            <option :value="null" />
            <option v-for="coin in coins" :key="coin.id" :value="coin.id">{{ coin.symbol }}</option>
          </select-input>
          <text-input v-model="form.coin_two_price" :error="form.errors.coin_two_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price" />
          <text-input v-model="form.coin_two_from_asset" :error="form.errors.coin_two_from_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="From Asset" />
          <text-input v-model="form.coin_two_to_asset" :error="form.errors.coin_two_to_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="To Asset" />
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <h2 class="text-2xl font-bold">Coin Three</h2>
        </div>
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.coin_three_id" :error="form.errors.coin_three_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Symbol">
            <option :value="null" />
            <option v-for="coin in coins" :key="coin.id" :value="coin.id">{{ coin.symbol }}</option>
          </select-input>
          <text-input v-model="form.coin_three_price" :error="form.errors.coin_three_price" class="pb-8 pr-6 w-full lg:w-1/2" label="Coin Price" />
          <text-input v-model="form.coin_three_from_asset" :error="form.errors.coin_three_from_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="From Asset" />
          <text-input v-model="form.coin_three_to_asset" :error="form.errors.coin_three_to_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="To Asset" />
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
        coin_one_from_asset: null,
        coin_one_to_asset: null,
        coin_two_id: null,
        coin_two_price: null,
        coin_two_from_asset: null,
        coin_two_to_asset: null,
        coin_three_id: null,
        coin_three_price: null,
        coin_three_from_asset: null,
        coin_three_to_asset: null,
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

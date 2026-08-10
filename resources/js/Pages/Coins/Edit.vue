<template>
  <div>
    <Head :title="symbol" />
    <h1 class="mb-8 text-3xl font-bold">
      <Link class="text-slate-400 hover:text-slate-700" href="/coins">Coins</Link>
      <span class="text-slate-400 font-medium">/</span>
      {{ symbol }}
    </h1>
    <trashed-message v-if="coin.deleted_at" class="mb-6" @restore="restore"> This coin has been deleted. </trashed-message>
    <div class="max-w-3xl bg-white rounded-md shadow overflow-hidden">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.base_asset" :error="form.errors.base_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="Base Asset" />
          <text-input v-model="form.quote_asset" :error="form.errors.quote_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="Quote Asset" />
        </div>
        <div class="flex items-center px-8 py-4 bg-gray-50 border-t border-gray-100">
          <button v-if="!coin.deleted_at" class="text-red-600 hover:underline" tabindex="-1" type="button" @click="destroy">Delete Coin</button>
          <loading-button :loading="form.processing" class="btn-primary ml-auto" type="submit">Update Coin</loading-button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import TrashedMessage from '@/Shared/TrashedMessage.vue'

export default {
  components: {
    Head,
    Icon,
    Link,
    LoadingButton,
    TextInput,
    TrashedMessage,
  },
  layout: Layout,
  props: {
    coin: Object,
  },
  remember: 'form',
  data() {
    return {
      form: this.$inertia.form({
        base_asset: this.coin.base_asset,
        quote_asset: this.coin.quote_asset,
        deleted_at: this.coin.deleted_at,
      }),
    }
  },
  computed: {
    symbol() {
      return `${this.form.base_asset}${this.form.quote_asset}`
    },
  },
  methods: {
    update() {
      this.form.put(`/coins/${this.coin.id}`)
    },
    destroy() {
      if (confirm('Are you sure you want to delete this coin?')) {
        this.$inertia.delete(`/coins/${this.coin.id}`)
      }
    },
    restore() {
      if (confirm('Are you sure you want to restore this coin?')) {
        this.$inertia.put(`/coins/${this.coin.id}/restore`)
      }
    },
  },
}
</script>

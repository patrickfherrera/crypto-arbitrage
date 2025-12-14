<template>
  <div>
    <Head title="Create Coins" />
    <h1 class="mb-8 text-3xl font-bold">
      <Link class="text-indigo-400 hover:text-indigo-600" href="/coins">Coins</Link>
      <span class="text-indigo-400 font-medium">/</span> Create
    </h1>
    <div class="max-w-3xl bg-white rounded-md shadow overflow-hidden">
      <form @submit.prevent="store">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.base_asset" :error="form.errors.base_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="Base Asset" />
          <text-input v-model="form.quote_asset" :error="form.errors.quote_asset" class="pb-8 pr-6 w-full lg:w-1/2" label="Quote Asset" />
          <select-input v-model="form.enabled" :error="form.errors.enabled" class="pb-8 pr-6 w-full lg:w-1/2" label="Enabled">
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select-input>
          <text-input v-model="form.transfer_fee" :error="form.errors.transfer_fee" class="pb-8 pr-6 w-full lg:w-1/2" label="Transfer Fee" />
        </div>
        <div class="flex items-center justify-end px-8 py-4 bg-gray-50 border-t border-gray-100">
          <loading-button :loading="form.processing" class="btn-indigo" type="submit">Create Coins</loading-button>
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
  remember: 'form',
  data() {
    return {
      form: this.$inertia.form({
        base_asset: null,
        quote_asset: null,
        transfer_fee: null,
        enabled: 1
      }),
    }
  },
  methods: {
    store() {
      this.form.post('/coins')
    },
  },
}
</script>

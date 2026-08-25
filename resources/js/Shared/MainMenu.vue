<template>
  <div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/coins">
        <div :class="isUrl('coins') ? 'text-white' : 'text-slate-400 group-hover:text-white'">Coins</div>
      </Link>
    </div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/arbitrages">
        <div :class="isUrl('arbitrages') ? 'text-white' : 'text-slate-400 group-hover:text-white'">Arbitrage</div>
      </Link>
    </div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/arbitrage-logs">
        <div :class="isUrl('arbitrage-logs') ? 'text-white' : 'text-slate-400 group-hover:text-white'">Arbitrage Logs</div>
      </Link>
    </div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/live-trades">
        <div :class="isUrl('live-trades') ? 'text-white' : 'text-slate-400 group-hover:text-white'">Live Trades</div>
      </Link>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    Link,
  },
  methods: {
    isUrl(...urls) {
      let currentUrl = this.$page.url.substr(1)
      if (urls[0] === '') {
        return currentUrl === ''
      }
      return urls.filter((url) => currentUrl.startsWith(url)).length
    },
  },
}
</script>

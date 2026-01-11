<template>
  <div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/coins">
<!--        <icon name="coins" class="mr-2 w-4 h-4" :class="isUrl('coins') ? 'fill-white' : 'fill-indigo-400 group-hover:fill-white'" />-->
        <div :class="isUrl('coins') ? 'text-white' : 'text-indigo-300 group-hover:text-white'">Coins</div>
      </Link>
    </div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/arbitrages">
<!--        <icon name="arbitrage" class="mr-2 w-4 h-4" :class="isUrl('arbitrages') ? 'fill-white' : 'fill-indigo-400 group-hover:fill-white'" />-->
        <div :class="isUrl('arbitrages') ? 'text-white' : 'text-indigo-300 group-hover:text-white'">Arbitrage</div>
      </Link>
    </div>
    <div class="mb-4">
      <Link class="group flex items-center py-3" href="/arbitrage-logs">
<!--        <icon name="printer" class="mr-2 w-4 h-4" :class="isUrl('arbitrage-logs') ? 'fill-white' : 'fill-indigo-400 group-hover:fill-white'" />-->
        <div :class="isUrl('arbitrage-logs') ? 'text-white' : 'text-indigo-300 group-hover:text-white'">Arbitrage Logs</div>
      </Link>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'

export default {
  components: {
    Icon,
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

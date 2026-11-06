<template>
  <div class="popover-wrap" v-show="visible" :style="style">
    <slot />
  </div>
</template>

<script lang="ts" setup>
import { ref, computed, type CSSProperties } from 'vue'
const visible = ref(true)
const style = computed<CSSProperties>(() => ({ position: 'absolute', zIndex: 1200 }))
defineExpose({ visible })
</script>

<style scoped>
.popover-wrap { background: white; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:8px }
</style>

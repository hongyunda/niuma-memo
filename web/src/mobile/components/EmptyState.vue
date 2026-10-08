<script setup lang="ts">
defineProps<{ description?: string; image?: string }>()
</script>

<template>
  <van-empty :image="image || 'default'" :description="description || '空空如也'">
    <slot />
  </van-empty>
</template>

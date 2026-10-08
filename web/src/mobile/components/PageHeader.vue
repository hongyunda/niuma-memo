<script setup lang="ts">
import { useRouter } from 'vue-router'

const props = withDefaults(defineProps<{ title: string; back?: boolean; fallback?: string; fixed?: boolean }>(), {
  back: true,
  fallback: '/',
  fixed: true,
})
const router = useRouter()

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace(props.fallback)
}
</script>

<template>
  <van-nav-bar :title="title" :left-arrow="back" :fixed="fixed" :placeholder="fixed" safe-area-inset-top @click-left="goBack">
    <template #right><slot name="right" /></template>
  </van-nav-bar>
</template>

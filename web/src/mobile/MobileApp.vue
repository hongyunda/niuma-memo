<script setup lang="ts">
import { useAuthBootstrap } from '@/shared/composables/useAuthBootstrap'
import MobileLayout from './layouts/MobileLayout.vue'
useAuthBootstrap()
</script>

<template>
  <div class="mobile">
    <MobileLayout>
      <router-view />
    </MobileLayout>
  </div>
</template>

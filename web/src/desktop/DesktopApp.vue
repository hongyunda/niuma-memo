<script setup lang="ts">
import { zhCN, dateZhCN } from 'naive-ui'
import { useAuthBootstrap } from '@/shared/composables/useAuthBootstrap'
import { themeOverrides } from './theme'
import DesktopLayout from './layouts/DesktopLayout.vue'
useAuthBootstrap()
</script>

<template>
  <n-config-provider :theme-overrides="themeOverrides" :locale="zhCN" :date-locale="dateZhCN" class="desktop">
    <n-dialog-provider>
      <n-message-provider placement="bottom">
        <DesktopLayout>
          <router-view />
        </DesktopLayout>
      </n-message-provider>
    </n-dialog-provider>
  </n-config-provider>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { pushApi, settingApi } from '@/shared/api'
import { useAppStore } from '@/shared/stores/app'
import { useUserStore } from '@/shared/stores/user'
import { CHANNELS, fileSize } from '@/shared/utils/format'
import { currentSubscription, pushSupported, subscribePush, unsubscribePush } from '@/shared/utils/push'
import { feedback } from '@/shared/ui/feedback'
import PasswordDialog from '@/desktop/dialogs/PasswordDialog.vue'

const app = useAppStore()
const userStore = useUserStore()
const form = ref({ bark_key: '', bark_server: '', wecom_webhook: '', default_channels: [] as string[] })
const saving = ref(false)
const testing = ref('')
const webpushOn = ref(false)
const webpushBusy = ref(false)
const showPassword = ref(false)

async function load() {
  const s = await app.fetchSettings(true)
  form.value = { bark_key: s.push_config.bark_key || '', bark_server: s.push_config.bark_server || '', wecom_webhook: s.push_config.wecom_webhook || '', default_channels: s.push_config.default_channels || ['webpush'] }
  webpushOn.value = !!(await currentSubscription())
}
async function save() {
  saving.value = true
  try {
    await settingApi.updatePush(form.value)
    feedback.success('已保存')
    app.fetchSettings(true)
  } finally {
    saving.value = false
  }
}
async function test(channel: string) {
  testing.value = channel
  try {
    if (channel !== 'webpush') await settingApi.updatePush(form.value)
    await pushApi.test(channel)
    feedback.success('已发送，看看手机')
  } catch {
    /* 已提示 */
  } finally {
    testing.value = ''
  }
}
async function toggleWebPush(on: boolean) {
  webpushBusy.value = true
  try {
    if (on) {
      await subscribePush((await app.fetchSettings()).vapid_public)
      webpushOn.value = true
      feedback.success('已开启')
    } else {
      await unsubscribePush()
      webpushOn.value = false
    }
    app.fetchSettings(true)
  } catch (e) {
    feedback.error((e as Error).message)
  } finally {
    webpushBusy.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="dpage">
    <div class="dpage-inner narrow">
      <div class="dpage-head"><h1>设置</h1></div>

      <n-card title="账号" :bordered="false" class="mb-4">
        <div class="flex items-center gap-4">
          <n-avatar round :size="48" color="#2b7fff" style="font-size: 20px">{{ (userStore.user?.nickname || userStore.user?.username || '?').slice(0, 1) }}</n-avatar>
          <div class="flex-1">
            <div class="font-semibold text-base">{{ userStore.user?.nickname || userStore.user?.username }}</div>
            <div class="text-xs" style="color: var(--t-3)">@{{ userStore.user?.username }} · 上次登录 {{ userStore.user?.last_login_at || '—' }}</div>
          </div>
          <n-button secondary size="small" @click="showPassword = true">修改密码</n-button>
        </div>
      </n-card>

      <n-card title="提醒推送" :bordered="false" class="mb-4">
        <n-form label-placement="left" label-width="110" :show-feedback="false" class="flex flex-col gap-4">
          <n-form-item label="浏览器通知">
            <div class="flex items-center gap-3">
              <n-switch :value="webpushOn" :loading="webpushBusy" :disabled="!pushSupported()" @update:value="toggleWebPush" />
              <span class="hint">{{ pushSupported() ? '本设备通过系统通知栏接收提醒' : '当前浏览器不支持' }}</span>
              <n-button v-if="webpushOn" size="tiny" tertiary @click="test('webpush')">发送测试</n-button>
            </div>
          </n-form-item>
          <n-form-item label="Bark Key">
            <div class="flex gap-2 w-full">
              <n-input v-model:value="form.bark_key" placeholder="iPhone 装 Bark App，复制里面的 Key" clearable />
              <n-button secondary :loading="testing === 'bark'" :disabled="!form.bark_key" @click="test('bark')">测试</n-button>
            </div>
          </n-form-item>
          <n-form-item v-if="form.bark_key" label="Bark 服务器"><n-input v-model:value="form.bark_server" placeholder="自建才填，默认 https://api.day.app" clearable /></n-form-item>
          <n-form-item label="企业微信机器人">
            <div class="flex gap-2 w-full">
              <n-input v-model:value="form.wecom_webhook" placeholder="群机器人 Webhook 地址" clearable />
              <n-button secondary :loading="testing === 'wecom'" :disabled="!form.wecom_webhook" @click="test('wecom')">测试</n-button>
            </div>
          </n-form-item>
          <n-form-item label="新提醒默认方式">
            <n-checkbox-group v-model:value="form.default_channels"><div class="flex gap-4"><n-checkbox v-for="(l, k) in CHANNELS" :key="k" :value="k" :label="l" /></div></n-checkbox-group>
          </n-form-item>
          <div class="flex justify-end"><n-button type="primary" :loading="saving" @click="save">保存推送设置</n-button></div>
        </n-form>
      </n-card>

      <n-card title="语音转文字" :bordered="false" class="mb-4">
        <div class="kv"><span>服务商</span><b>{{ app.settings?.asr.provider === 'tencent' ? '腾讯云（每月 10 小时免费）' : app.settings?.asr.provider === 'volcengine' ? '火山引擎（按量 0.8 元/小时）' : '未配置' }}</b></div>
        <div class="kv"><span>状态</span><b>{{ app.settings?.asr.enabled ? '已启用，录音上传后自动转写' : '在服务器 .env 里填入密钥后启用' }}</b></div>
        <div class="kv"><span>音频转码 (ffmpeg)</span><b>{{ app.settings?.ffmpeg ? '可用' : '未安装，手机录音可能无法跨端播放' }}</b></div>
      </n-card>

      <n-card title="数据" :bordered="false">
        <div class="kv"><span>附件占用</span><b>{{ app.settings ? `${fileSize(app.settings.storage.used)} · ${app.settings.storage.count} 个文件` : '—' }}</b></div>
        <div class="kv"><span>单文件上限</span><b>{{ app.settings ? `${app.settings.upload_max_mb} MB` : '—' }}</b></div>
        <div class="kv"><span>手机端</span><b>用手机浏览器打开同一地址，「添加到主屏幕」即可像 App 一样使用</b></div>
      </n-card>
    </div>
    <PasswordDialog v-model:show="showPassword" />
  </div>
</template>

<style scoped>
.narrow { max-width: 820px; }
.hint { font-size: 12px; color: var(--t-3); }
.kv { display: flex; gap: 16px; padding: 8px 0; border-bottom: 1px solid var(--b-2); font-size: 14px; }
.kv:last-child { border-bottom: 0; }
.kv span { width: 140px; color: var(--t-3); flex: 0 0 auto; }
.kv b { font-weight: 500; color: var(--t-1); }
</style>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { showConfirmDialog, showToast } from 'vant'
import { authApi, pushApi, settingApi } from '@/shared/api'
import { useAppStore } from '@/shared/stores/app'
import { useUserStore } from '@/shared/stores/user'
import { CHANNELS, fileSize } from '@/shared/utils/format'
import { currentSubscription, isIOS, isStandalone, pushSupported, subscribePush, unsubscribePush } from '@/shared/utils/push'
import PageHeader from '@/mobile/components/PageHeader.vue'

const router = useRouter()
const app = useAppStore()
const userStore = useUserStore()

const pushForm = ref({ bark_key: '', bark_server: '', wecom_webhook: '', default_channels: [] as string[] })
const savingPush = ref(false)
const testing = ref('')
const webpushOn = ref(false)
const webpushBusy = ref(false)
const showPassword = ref(false)
const pwd = ref({ old_password: '', new_password: '', confirm: '' })

async function load() {
  const s = await app.fetchSettings(true)
  pushForm.value = {
    bark_key: s.push_config.bark_key || '',
    bark_server: s.push_config.bark_server || '',
    wecom_webhook: s.push_config.wecom_webhook || '',
    default_channels: s.push_config.default_channels || ['webpush'],
  }
  webpushOn.value = !!(await currentSubscription())
}

async function savePush() {
  savingPush.value = true
  try {
    await settingApi.updatePush(pushForm.value)
    showToast({ message: '已保存', icon: 'success' })
    app.fetchSettings(true)
  } finally {
    savingPush.value = false
  }
}

async function test(channel: string) {
  testing.value = channel
  try {
    if (channel !== 'webpush') await settingApi.updatePush(pushForm.value)
    await pushApi.test(channel)
    showToast({ message: '已发送，看看手机', icon: 'success' })
  } catch {
    /* 拦截器已提示 */
  } finally {
    testing.value = ''
  }
}

async function toggleWebPush(on: boolean) {
  webpushBusy.value = true
  try {
    if (on) {
      const s = await app.fetchSettings()
      await subscribePush(s.vapid_public)
      webpushOn.value = true
      showToast({ message: '已开启', icon: 'success' })
    } else {
      await unsubscribePush()
      webpushOn.value = false
    }
    app.fetchSettings(true)
  } catch (e) {
    showToast((e as Error).message)
  } finally {
    webpushBusy.value = false
  }
}

async function changePassword() {
  if (pwd.value.new_password.length < 6) return showToast('新密码至少 6 位')
  if (pwd.value.new_password !== pwd.value.confirm) return showToast('两次输入的新密码不一致')
  await authApi.password(pwd.value.old_password, pwd.value.new_password)
  showToast({ message: '密码已修改', icon: 'success' })
  showPassword.value = false
  pwd.value = { old_password: '', new_password: '', confirm: '' }
}

async function logout() {
  try {
    await showConfirmDialog({ title: '退出登录', message: '确定退出？' })
  } catch {
    return
  }
  await userStore.logout()
  router.replace('/login')
}

onMounted(load)
</script>

<template>
  <div class="page">
    <PageHeader title="我的" :back="false" />
    <div class="desktop-container !pt-0">
      <div class="mx-3 mt-2 bg-white rounded-xl p-4 flex items-center gap-3">
        <div class="avatar">{{ (userStore.user?.nickname || userStore.user?.username || '?').slice(0, 1) }}</div>
        <div class="flex-1">
          <div class="font-medium text-base">{{ userStore.user?.nickname || userStore.user?.username }}</div>
          <div class="text-xs text-gray-400">@{{ userStore.user?.username }}</div>
        </div>
        <van-button size="small" round plain @click="showPassword = true">改密码</van-button>
      </div>

      <div class="group-title">提醒推送</div>
      <van-cell-group inset>
        <van-cell title="浏览器通知" :label="pushSupported() ? (isStandalone() ? '系统级通知，锁屏可见' : isIOS() ? 'iPhone 需先添加到主屏幕再从桌面打开' : '本设备接收通知') : '当前浏览器不支持'">
          <template #right-icon>
            <van-switch :model-value="webpushOn" :loading="webpushBusy" :disabled="!pushSupported()" size="22" @update:model-value="toggleWebPush" />
          </template>
        </van-cell>
        <van-cell v-if="webpushOn" title="发送测试通知" is-link @click="test('webpush')" />
        <van-field v-model="pushForm.bark_key" label="Bark Key" placeholder="iPhone 装 Bark App，复制里面的 Key" clearable>
          <template #button><van-button size="small" :loading="testing === 'bark'" :disabled="!pushForm.bark_key" @click="test('bark')">测试</van-button></template>
        </van-field>
        <van-field v-if="pushForm.bark_key" v-model="pushForm.bark_server" label="Bark 服务器" placeholder="自建才填，默认 https://api.day.app" clearable />
        <van-field v-model="pushForm.wecom_webhook" label="企业微信" placeholder="群机器人 Webhook 地址" clearable type="textarea" rows="1" autosize>
          <template #button><van-button size="small" :loading="testing === 'wecom'" :disabled="!pushForm.wecom_webhook" @click="test('wecom')">测试</van-button></template>
        </van-field>
        <van-cell title="新建提醒默认方式">
          <template #label>
            <van-checkbox-group v-model="pushForm.default_channels" direction="horizontal" class="mt-2">
              <van-checkbox v-for="(label, key) in CHANNELS" :key="key" :name="key" shape="square" icon-size="16">{{ label }}</van-checkbox>
            </van-checkbox-group>
          </template>
        </van-cell>
        <div class="p-3"><van-button type="primary" block round size="small" :loading="savingPush" @click="savePush">保存推送设置</van-button></div>
      </van-cell-group>

      <div class="group-title">语音转文字</div>
      <van-cell-group inset>
        <van-cell title="服务商" :value="app.settings?.asr.provider === 'tencent' ? '腾讯云（每月 10 小时免费）' : app.settings?.asr.provider === 'volcengine' ? '火山引擎（按量 0.8 元/小时）' : '未配置'" />
        <van-cell title="状态" :value="app.settings?.asr.enabled ? '已启用，录音上传后自动转写' : '在服务器 .env 里填入密钥后启用'" />
        <van-cell title="音频转码 (ffmpeg)" :value="app.settings?.ffmpeg ? '可用' : '未安装，手机录音可能无法跨端播放'" />
      </van-cell-group>

      <div class="group-title">数据</div>
      <van-cell-group inset>
        <van-cell title="附件占用" :value="app.settings ? `${fileSize(app.settings.storage.used)} · ${app.settings.storage.count} 个文件` : ''" />
        <van-cell title="单文件上限" :value="app.settings ? `${app.settings.upload_max_mb} MB` : ''" />
        <van-cell title="回收站" is-link to="/trash" />
      </van-cell-group>

      <div class="group-title">关于</div>
      <van-cell-group inset>
        <van-cell title="添加到手机桌面" label="Safari / Chrome 菜单里选「添加到主屏幕」，像 App 一样用" />
        <van-cell title="版本" value="0.1.0" />
      </van-cell-group>

      <div class="p-4"><van-button block round plain type="danger" @click="logout">退出登录</van-button></div>
    </div>

    <van-popup v-model:show="showPassword" position="bottom" round closeable safe-area-inset-bottom>
      <div class="p-4 pt-5">
        <h3 class="m-0 mb-3 text-base font-semibold">修改密码</h3>
        <van-cell-group inset class="!mx-0">
          <van-field v-model="pwd.old_password" type="password" label="原密码" placeholder="原密码" />
          <van-field v-model="pwd.new_password" type="password" label="新密码" placeholder="至少 6 位" />
          <van-field v-model="pwd.confirm" type="password" label="确认" placeholder="再输一次新密码" />
        </van-cell-group>
        <van-button type="primary" block round class="mt-4" @click="changePassword">确认修改</van-button>
      </div>
    </van-popup>
  </div>
</template>

<style scoped>
.avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--van-primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; }
.group-title { font-size: 13px; font-weight: 600; color: #646566; padding: 14px 16px 6px; }
</style>

<script setup lang="ts">
/**
 * TwoFactorSetup — мини-wizard для подключения 2FA TOTP.
 *
 * State-machine:
 *   idle      — 2FA выключена; кнопка «Включить 2FA».
 *   setup     — backend выдал secret + qr_uri; показываем QR + поле для
 *               подтверждающего 6-значного кода.
 *   confirmed — после успешного twoFactorConfirm: показываем recovery-коды.
 *   enabled   — 2FA уже включена; кнопки «Перегенерировать коды» / «Отключить».
 *
 * QR рендерится встроенным lean-qr (~3KB, без peer-dep). Host может
 * переопределить через slot `qr-code` (например для брендированной
 * картинки или canvas-варианта).
 */
import { computed, ref } from 'vue'
import { Copy, ShieldCheck, ShieldOff, RefreshCw } from 'lucide-vue-next'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { generate, correction } from 'lean-qr'
import { toSvgSource } from 'lean-qr/extras/svg'
import { adminToast } from '../../stores/toast'

interface Props {
  /** Включена ли 2FA на момент монтирования (auth.user.twoFactorEnabled). */
  enabled: boolean
}
const props = defineProps<Props>()

const emit = defineEmits<{
  /** 2FA подтверждена — host обновляет auth.user.twoFactorEnabled. */
  enabled: []
  /** 2FA отключена. */
  disabled: []
}>()

type Stage = 'idle' | 'setup' | 'confirmed' | 'enabled'
const stage = ref<Stage>(props.enabled ? 'enabled' : 'idle')

const secret = ref<string>('')
const qrUri = ref<string>('')
const code = ref<string>('')
const recoveryCodes = ref<string[]>([])
const password = ref<string>('')
const busy = ref<boolean>(false)
const error = ref<string>('')

async function startSetup(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.post<{
      secret: string
      qr_uri: string
      recovery_codes?: string[]
    }>('/profile/twoFactorEnable')
    secret.value = result.secret
    qrUri.value = result.qr_uri
    recoveryCodes.value = result.recovery_codes ?? []
    stage.value = 'setup'
  } catch {
    error.value = 'Не удалось инициализировать 2FA.'
  } finally {
    busy.value = false
  }
}

async function confirmCode(): Promise<void> {
  if (code.value.length < 4) return
  busy.value = true
  error.value = ''
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.post<{ recovery_codes?: string[] }>(
      '/profile/twoFactorConfirm',
      { code: code.value },
    )
    if (result.recovery_codes) recoveryCodes.value = result.recovery_codes
    stage.value = 'confirmed'
    adminToast.success('Двухфакторная аутентификация подключена.')
    emit('enabled')
  } catch {
    error.value = 'Неверный код. Попробуйте ещё раз.'
  } finally {
    busy.value = false
  }
}

async function disable(): Promise<void> {
  if (!window.confirm('Отключить 2FA? Аккаунт станет менее защищённым.')) return
  busy.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post('/profile/twoFactorDisable')
    stage.value = 'idle'
    adminToast.success('2FA отключена.')
    emit('disabled')
  } catch {
    adminToast.error('Не удалось отключить 2FA.')
  } finally {
    busy.value = false
  }
}

async function regenerate(): Promise<void> {
  if (password.value === '') {
    error.value = 'Введите текущий пароль.'
    return
  }
  busy.value = true
  error.value = ''
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.post<{ recovery_codes: string[] }>(
      '/profile/twoFactorRegenerateCodes',
      { password: password.value },
    )
    recoveryCodes.value = result.recovery_codes
    password.value = ''
    adminToast.success('Recovery-коды обновлены.')
  } catch {
    error.value = 'Неверный пароль.'
  } finally {
    busy.value = false
  }
}

async function copySecret(): Promise<void> {
  try {
    await navigator.clipboard.writeText(secret.value)
    adminToast.success('Secret скопирован.')
  } catch {
    adminToast.warning('Скопируйте вручную.')
  }
}

async function copyCodes(): Promise<void> {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
    adminToast.success('Recovery-коды скопированы.')
  } catch {
    adminToast.warning('Скопируйте вручную.')
  }
}

const formattedSecret = computed(() => secret.value.match(/.{1,4}/g)?.join(' ') ?? secret.value)

/**
 * Генерируем QR из otpauth-URI через lean-qr. Возвращаем SVG-string,
 * который вставляется через v-html. ECC=M (15%) — оптимум: достаточно
 * для пятен на экране, но без больших data-блоков.
 */
const qrSvg = computed<string>(() => {
  if (qrUri.value === '') return ''
  try {
    const code = generate(qrUri.value, { minCorrectionLevel: correction.M })
    return toSvgSource(code, { on: '#18181b', off: 'transparent', padX: 1, padY: 1 })
  } catch {
    return ''
  }
})
</script>

<template>
  <div class="admin-2fa">
    <!-- Idle: 2FA выключена -->
    <div v-if="stage === 'idle'" class="admin-2fa__panel">
      <p class="admin-2fa__lead">
        Двухфакторная аутентификация добавляет второй слой защиты — даже если пароль попадёт
        в чужие руки, без OTP-кода из приложения войти не получится.
      </p>
      <UidButton variant="primary" :loading="busy" @click="startSetup">
        <template #prepend><UidIcon :icon="ShieldCheck" :size="14" /></template>
        Включить 2FA
      </UidButton>
      <p v-if="error" class="admin-2fa__error">{{ error }}</p>
    </div>

    <!-- Setup: показываем secret + поле для кода -->
    <div v-else-if="stage === 'setup'" class="admin-2fa__panel">
      <ol class="admin-2fa__steps">
        <li>Откройте Authenticator-приложение (Google Authenticator, 1Password, Authy…).</li>
        <li>
          Добавьте новый аккаунт вручную, скопировав ключ:
          <div class="admin-2fa__secret">
            <code>{{ formattedSecret }}</code>
            <button type="button" class="admin-2fa__copy" @click="copySecret">
              <UidIcon :icon="Copy" :size="12" />
              Копировать
            </button>
          </div>
          <slot name="qr-code" :uri="qrUri">
            <div v-if="qrSvg" class="admin-2fa__qr" v-html="qrSvg" />
            <p v-else class="admin-2fa__hint">
              Либо отсканируйте QR с другого устройства — поделитесь URI:
              <code class="admin-2fa__uri">{{ qrUri }}</code>
            </p>
          </slot>
        </li>
        <li>
          Введите 6-значный код из приложения:
          <div class="admin-2fa__code-row">
            <UidInput
              v-model="code"
              placeholder="123456"
              maxlength="6"
              class="admin-2fa__code-input"
            />
            <UidButton
              variant="primary"
              :loading="busy"
              :disabled="code.length < 4"
              @click="confirmCode"
            >
              Подтвердить
            </UidButton>
          </div>
        </li>
      </ol>
      <p v-if="error" class="admin-2fa__error">{{ error }}</p>
    </div>

    <!-- Confirmed: показываем recovery-коды -->
    <div v-else-if="stage === 'confirmed'" class="admin-2fa__panel admin-2fa__panel--success">
      <p class="admin-2fa__lead">
        2FA активирована. Сохраните recovery-коды в безопасном месте — они нужны если вы
        потеряете доступ к Authenticator-приложению.
      </p>
      <div class="admin-2fa__codes">
        <code v-for="c in recoveryCodes" :key="c" class="admin-2fa__codes-item">{{ c }}</code>
      </div>
      <UidButton variant="ghost" @click="copyCodes">
        <template #prepend><UidIcon :icon="Copy" :size="14" /></template>
        Скопировать все
      </UidButton>
      <UidButton variant="primary" @click="stage = 'enabled'">Готово</UidButton>
    </div>

    <!-- Enabled: 2FA активна — manage -->
    <div v-else-if="stage === 'enabled'" class="admin-2fa__panel">
      <p class="admin-2fa__lead">
        2FA включена. Если у вас остался доступ к Authenticator app — всё в порядке.
      </p>
      <div class="admin-2fa__manage">
        <UidInput
          v-model="password"
          type="password"
          placeholder="Текущий пароль"
        />
        <UidButton variant="secondary" :loading="busy" @click="regenerate">
          <template #prepend><UidIcon :icon="RefreshCw" :size="14" /></template>
          Перегенерировать recovery-коды
        </UidButton>
        <UidButton variant="danger" :loading="busy" @click="disable">
          <template #prepend><UidIcon :icon="ShieldOff" :size="14" /></template>
          Отключить 2FA
        </UidButton>
      </div>
      <p v-if="error" class="admin-2fa__error">{{ error }}</p>
      <div v-if="recoveryCodes.length > 0" class="admin-2fa__codes">
        <code v-for="c in recoveryCodes" :key="c" class="admin-2fa__codes-item">{{ c }}</code>
      </div>
    </div>
  </div>
</template>

<style>
.admin-2fa { display: flex; flex-direction: column; gap: var(--uid-space-md); }
.admin-2fa__panel {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  background: var(--uid-surface-base);
}
.admin-2fa__panel--success {
  border-color: color-mix(in srgb, var(--uid-color-success, #10b981) 35%, transparent);
  background: color-mix(in srgb, var(--uid-color-success, #10b981) 5%, transparent);
}
.admin-2fa__lead { margin: 0; color: var(--uid-text-secondary); font-size: 14px; }
.admin-2fa__steps { padding-left: 20px; display: flex; flex-direction: column; gap: var(--uid-space-md); }
.admin-2fa__steps li { font-size: 14px; color: var(--uid-text-primary); }
.admin-2fa__secret {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-sm);
  margin-top: 6px;
  padding: 6px 10px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 13px;
}
.admin-2fa__copy {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  cursor: pointer;
  font-size: 11px;
  color: var(--uid-text-secondary);
}
.admin-2fa__copy:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-2fa__uri {
  display: block;
  margin-top: 6px;
  padding: 6px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-sm);
  font-size: 11px;
  word-break: break-all;
}
.admin-2fa__hint { margin: 6px 0 0; font-size: 12px; color: var(--uid-text-tertiary); }
.admin-2fa__qr {
  display: inline-block;
  margin-top: 8px;
  padding: 12px;
  background: #fff;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
}
.admin-2fa__qr svg { display: block; width: 200px; height: 200px; }
.admin-2fa__code-row {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-sm);
  margin-top: 6px;
}
.admin-2fa__code-input { width: 140px; }
.admin-2fa__error {
  margin: 0;
  color: var(--uid-color-danger, #dc2626);
  font-size: 13px;
}
.admin-2fa__codes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 6px;
}
.admin-2fa__codes-item {
  padding: 6px 8px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-sm);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 12px;
  text-align: center;
}
.admin-2fa__manage {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-sm);
  align-items: center;
}
</style>

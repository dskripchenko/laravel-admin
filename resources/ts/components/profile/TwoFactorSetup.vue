<script setup lang="ts">
/**
 * TwoFactorSetup — the small wizard that turns TOTP-based 2FA on.
 *
 * Its states:
 *   idle      — 2FA is off, and there is an "Enable 2FA" button.
 *   setup     — the backend issued a secret and a qr_uri; the QR code is shown
 *               along with the field for the confirming six-digit code.
 *   confirmed — after a successful twoFactorConfirm: the recovery codes.
 *   enabled   — 2FA is already on, with "Regenerate the codes" and "Disable".
 *
 * The QR code is drawn by the bundled lean-qr, about 3 KB and with no peer
 * dependency. A host may replace it through the `qr-code` slot — for a branded
 * image or a canvas version, say.
 */
import { computed, ref } from 'vue'
import { Copy, ShieldCheck, ShieldOff, RefreshCw } from 'lucide-vue-next'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { generate, correction } from 'lean-qr'
import { toSvgSource } from 'lean-qr/extras/svg'
import { adminToast } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  /** Whether 2FA was on when this mounted — auth.user.twoFactorEnabled. */
  enabled: boolean
}
const props = defineProps<Props>()

const emit = defineEmits<{
  /** 2FA has been confirmed; the host updates auth.user.twoFactorEnabled. */
  enabled: []
  /** 2FA has been switched off. */
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
    error.value = tr('Не удалось инициализировать 2FA.')
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
    adminToast.success(tr('Двухфакторная аутентификация подключена.'))
    emit('enabled')
  } catch {
    error.value = tr('Неверный код. Попробуйте ещё раз.')
  } finally {
    busy.value = false
  }
}

async function disable(): Promise<void> {
  // The backend wants the password as confirmation; without it the button quietly returned a 422.
  if (password.value === '') {
    error.value = tr('Введите текущий пароль.')
    return
  }
  if (!window.confirm(tr('Отключить 2FA? Аккаунт станет менее защищённым.'))) return
  busy.value = true
  error.value = ''
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post('/profile/twoFactorDisable', { password: password.value })
    stage.value = 'idle'
    adminToast.success(tr('2FA отключена.'))
    emit('disabled')
  } catch {
    adminToast.error(tr('Не удалось отключить 2FA.'))
  } finally {
    busy.value = false
  }
}

async function regenerate(): Promise<void> {
  if (password.value === '') {
    error.value = tr('Введите текущий пароль.')
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
    adminToast.success(tr('Recovery-коды обновлены.'))
  } catch {
    error.value = tr('Неверный пароль.')
  } finally {
    busy.value = false
  }
}

async function copySecret(): Promise<void> {
  try {
    await navigator.clipboard.writeText(secret.value)
    adminToast.success(tr('Secret скопирован.'))
  } catch {
    adminToast.warning(tr('Скопируйте вручную.'))
  }
}

async function copyCodes(): Promise<void> {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
    adminToast.success(tr('Recovery-коды скопированы.'))
  } catch {
    adminToast.warning(tr('Скопируйте вручную.'))
  }
}

const formattedSecret = computed(() => secret.value.match(/.{1,4}/g)?.join(' ') ?? secret.value)

/**
 * Builds the QR code from the otpauth URI with lean-qr and returns an SVG
 * string, inserted through v-html. ECC=M, 15%, is the sweet spot: enough for
 * smudges on a screen without inflating the data blocks.
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
        {{ tr('Двухфакторная аутентификация добавляет второй слой защиты — даже если пароль попадёт') }}
        {{ tr('в чужие руки, без OTP-кода из приложения войти не получится.') }}
      </p>
      <UidButton variant="primary" :loading="busy" data-testid="2fa-enable" @click="startSetup">
        <template #prepend><UidIcon :icon="ShieldCheck" :size="14" /></template>
        {{ tr('Включить 2FA') }}
      </UidButton>
      <p v-if="error" class="admin-2fa__error">{{ error }}</p>
    </div>

    <!-- Setup: показываем secret + поле для кода -->
    <div v-else-if="stage === 'setup'" class="admin-2fa__panel">
      <ol class="admin-2fa__steps">
        <li>{{ tr('Откройте Authenticator-приложение (Google Authenticator, 1Password, Authy…).') }}</li>
        <li>
          {{ tr('Добавьте новый аккаунт вручную, скопировав ключ:') }}
          <div class="admin-2fa__secret" data-testid="2fa-secret">
            <code>{{ formattedSecret }}</code>
            <button type="button" class="admin-2fa__copy" @click="copySecret">
              <UidIcon :icon="Copy" :size="12" />
              {{ tr('Копировать') }}
            </button>
          </div>
          <slot name="qr-code" :uri="qrUri">
            <div v-if="qrSvg" class="admin-2fa__qr" v-html="qrSvg" />
            <p v-else class="admin-2fa__hint">
              {{ tr('Либо отсканируйте QR с другого устройства — поделитесь URI:') }}
              <code class="admin-2fa__uri">{{ qrUri }}</code>
            </p>
          </slot>
        </li>
        <li>
          {{ tr('Введите 6-значный код из приложения:') }}
          <div class="admin-2fa__code-row">
            <UidInput
              v-model="code"
              data-testid="2fa-code"
              placeholder="123456"
              maxlength="6"
              class="admin-2fa__code-input"
            />
            <UidButton
              variant="primary"
              :loading="busy"
              :disabled="code.length < 4"
              data-testid="2fa-confirm"
              @click="confirmCode"
            >
              {{ tr('Подтвердить') }}
            </UidButton>
          </div>
        </li>
      </ol>
      <p v-if="error" class="admin-2fa__error">{{ error }}</p>
    </div>

    <!-- Confirmed: показываем recovery-коды -->
    <div v-else-if="stage === 'confirmed'" class="admin-2fa__panel admin-2fa__panel--success">
      <p class="admin-2fa__lead">
        {{ tr('2FA активирована. Сохраните recovery-коды в безопасном месте — они нужны если вы') }}
        {{ tr('потеряете доступ к Authenticator-приложению.') }}
      </p>
      <div class="admin-2fa__codes">
        <code v-for="c in recoveryCodes" :key="c" class="admin-2fa__codes-item">{{ c }}</code>
      </div>
      <UidButton variant="ghost" @click="copyCodes">
        <template #prepend><UidIcon :icon="Copy" :size="14" /></template>
        {{ tr('Скопировать все') }}
      </UidButton>
      <UidButton variant="primary" @click="stage = 'enabled'">{{ tr('Готово') }}</UidButton>
    </div>

    <!-- Enabled: 2FA активна — manage -->
    <div v-else-if="stage === 'enabled'" class="admin-2fa__panel">
      <p class="admin-2fa__lead">
        {{ tr('2FA включена. Если у вас остался доступ к Authenticator app — всё в порядке.') }}
      </p>
      <div class="admin-2fa__manage">
        <UidInput
          v-model="password"
          type="password"
          :placeholder="tr('Текущий пароль')"
        />
        <UidButton variant="secondary" :loading="busy" @click="regenerate">
          <template #prepend><UidIcon :icon="RefreshCw" :size="14" /></template>
          {{ tr('Перегенерировать recovery-коды') }}
        </UidButton>
        <UidButton variant="danger" :loading="busy" data-testid="2fa-disable" @click="disable">
          <template #prepend><UidIcon :icon="ShieldOff" :size="14" /></template>
          {{ tr('Отключить 2FA') }}
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

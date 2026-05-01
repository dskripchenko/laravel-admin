<script setup lang="ts">
/**
 * 2FA-форма: 6-cell mono TOTP-input + alt recovery-code.
 *
 * Эталон — docs/design_handoff_laravel_admin/screens-secondary.jsx (TwoFactor):
 * 6 input'ов 44×52, IBM Plex Mono 22px, при заполнении одного — фокус
 * автопереходит на следующий, paste-event раскладывает 6 цифр сразу.
 */
import { computed, nextTick, ref } from 'vue'
import { UidAlert, UidButton, UidInput } from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import { ApiError, NetworkError, ValidationError } from '../../api/errors'

const emit = defineEmits<{
  success: []
  cancel: []
}>()

const auth = useAuthStore()

const mode = ref<'totp' | 'recovery'>('totp')
const cells = ref<string[]>(['', '', '', '', '', ''])
const recoveryCode = ref('')
const cellRefs = ref<HTMLInputElement[]>([])

const submitting = ref(false)
const generalError = ref<string | null>(null)
const remaining = ref<number | null>(null)

const totpCode = computed(() => cells.value.join(''))
const isValid = computed(() => {
  if (mode.value === 'totp') return totpCode.value.length === 6
  return recoveryCode.value.trim().length > 0
})

function setCellRef(idx: number, el: Element | null): void {
  if (el instanceof HTMLInputElement) cellRefs.value[idx] = el
}

function onCellInput(idx: number, event: Event): void {
  const target = event.target as HTMLInputElement
  // Только цифры; обрезаем до 1 символа.
  const digit = target.value.replace(/\D/g, '').slice(-1)
  cells.value[idx] = digit
  target.value = digit
  if (digit && idx < cells.value.length - 1) {
    void nextTick(() => cellRefs.value[idx + 1]?.focus())
  }
  if (totpCode.value.length === cells.value.length) {
    void submit()
  }
}

function onCellKeydown(idx: number, event: KeyboardEvent): void {
  if (event.key === 'Backspace' && !cells.value[idx] && idx > 0) {
    void nextTick(() => cellRefs.value[idx - 1]?.focus())
  } else if (event.key === 'ArrowLeft' && idx > 0) {
    cellRefs.value[idx - 1]?.focus()
    event.preventDefault()
  } else if (event.key === 'ArrowRight' && idx < cells.value.length - 1) {
    cellRefs.value[idx + 1]?.focus()
    event.preventDefault()
  }
}

function onPaste(event: ClipboardEvent): void {
  const pasted = event.clipboardData?.getData('text') ?? ''
  const digits = pasted.replace(/\D/g, '').slice(0, cells.value.length)
  if (digits.length === 0) return
  event.preventDefault()
  for (let i = 0; i < cells.value.length; i++) {
    cells.value[i] = digits[i] ?? ''
  }
  void nextTick(() => {
    cellRefs.value[Math.min(digits.length, cells.value.length - 1)]?.focus()
    if (digits.length === cells.value.length) void submit()
  })
}

async function submit(): Promise<void> {
  if (submitting.value || !isValid.value) return
  submitting.value = true
  generalError.value = null

  try {
    if (mode.value === 'totp') {
      await auth.twoFactorChallenge(totpCode.value)
    } else {
      const res = await auth.twoFactorRecovery(recoveryCode.value.trim())
      remaining.value = res.remaining
    }
    emit('success')
  } catch (err) {
    if (err instanceof ValidationError) {
      generalError.value = err.firstFieldMessage() ?? 'Неверный код'
    } else if (err instanceof NetworkError) {
      generalError.value = 'Нет соединения с сервером'
    } else if (err instanceof ApiError) {
      generalError.value = err.message || 'Не удалось проверить код'
    } else {
      generalError.value = (err as Error).message
    }
  } finally {
    submitting.value = false
  }
}

function cancel(): void {
  auth.cancelChallenge()
  emit('cancel')
}

function switchMode(): void {
  mode.value = mode.value === 'totp' ? 'recovery' : 'totp'
  generalError.value = null
}
</script>

<template>
  <form class="admin-auth-card__bd" novalidate @submit.prevent="submit">
    <UidAlert
      v-if="generalError"
      variant="danger"
      class="admin-auth-card__alert"
      role="alert"
    >
      {{ generalError }}
    </UidAlert>
    <UidAlert
      v-if="remaining !== null"
      variant="info"
      class="admin-auth-card__alert"
      role="status"
    >
      Использован recovery-код. Осталось: {{ remaining }}
    </UidAlert>

    <div v-if="mode === 'totp'" class="admin-code-input" @paste="onPaste">
      <input
        v-for="(_, idx) in cells"
        :key="idx"
        :ref="(el) => setCellRef(idx, el as Element | null)"
        :value="cells[idx]"
        type="text"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="1"
        :disabled="submitting"
        :aria-label="`Цифра ${idx + 1}`"
        @input="onCellInput(idx, $event)"
        @keydown="onCellKeydown(idx, $event)"
      />
    </div>

    <UidInput
      v-else
      v-model="recoveryCode"
      type="text"
      label="Recovery-код"
      autocomplete="one-time-code"
      :disabled="submitting"
      name="recovery-code"
    />

    <UidButton
      type="submit"
      variant="primary"
      size="lg"
      :loading="submitting"
      :disabled="submitting || !isValid"
    >
      {{ submitting ? 'Проверка…' : 'Подтвердить' }}
    </UidButton>

    <div class="admin-auth-card__row">
      <button type="button" class="admin-auth-card__link" @click="switchMode">
        <template v-if="mode === 'totp'">Использовать recovery-код</template>
        <template v-else>Вернуться к коду из приложения</template>
      </button>
      <button type="button" class="admin-auth-card__link" @click="cancel">
        Отмена
      </button>
    </div>
  </form>
</template>

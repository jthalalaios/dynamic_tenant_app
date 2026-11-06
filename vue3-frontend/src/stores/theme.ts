import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { applyTheme, availableThemes } from '../services/theme'
import { storage } from '../helpers/storage'
import { fixedTheme, initialThemes, isStandalone, isThemeName } from '../config/edition'
import type { TenantType } from '../types/models'

const THEME_KEY = 'theme'

/**
 * Active visual theme.
 *
 * - Multi mode: guests may preview any enabled theme; once signed in, the
 *   theme is locked to the tenant type (a clinic always sees the doctor theme).
 * - Standalone mode: the theme is fixed and never unlocks (see config/edition).
 */
export const useThemeStore = defineStore('theme', () => {
  const enabled = ref<TenantType[]>([...initialThemes])
  const stored = storage.get(THEME_KEY)
  const currentThemeName = ref<TenantType>(
    fixedTheme ?? (isThemeName(stored) && enabled.value.includes(stored) ? stored : enabled.value[0])
  )
  const locked = ref(isStandalone)

  const available = computed(() => availableThemes.filter((t) => enabled.value.includes(t.name)))
  /** Whether a guest gets a theme picker (and a workspace type choice on sign up) */
  const canSwitch = computed(() => !locked.value && available.value.length > 1)

  function init(): void {
    applyTheme(currentThemeName.value)
  }

  function setTheme(name: TenantType): void {
    if (locked.value || !enabled.value.includes(name)) return
    currentThemeName.value = name
    storage.set(THEME_KEY, name)
    applyTheme(name)
  }

  function lockTo(type: TenantType): void {
    currentThemeName.value = type
    locked.value = true
    applyTheme(type)
  }

  function unlock(): void {
    if (isStandalone) {
      lockTo(fixedTheme!)
      return
    }
    locked.value = false
    const preview = storage.get(THEME_KEY)
    const next = isThemeName(preview) && enabled.value.includes(preview) ? preview : enabled.value[0]
    if (next !== currentThemeName.value) setTheme(next)
  }

  /** Narrow the multi-mode offer to the themes the API actually serves (GET /app) */
  function setEnabled(themes: readonly string[]): void {
    if (isStandalone) return
    const next = initialThemes.filter((t) => themes.includes(t))
    if (!next.length) return
    enabled.value = next
    if (!locked.value && !next.includes(currentThemeName.value)) setTheme(next[0])
  }

  return { currentThemeName, locked, enabled, available, canSwitch, isStandalone, init, setTheme, lockTo, unlock, setEnabled }
})

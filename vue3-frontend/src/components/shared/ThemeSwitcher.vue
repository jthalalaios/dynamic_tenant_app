<template>
  <label v-if="themeStore.canSwitch" class="theme-switcher">
    <span class="label">{{ t('common.theme') }}</span>
    <select :value="themeStore.currentThemeName" @change="themeStore.setTheme(($event.target as HTMLSelectElement).value as TenantType)">
      <option v-for="theme in themeStore.available" :key="theme.name" :value="theme.name">
        {{ t(`themes.${theme.name}`) }}
      </option>
    </select>
  </label>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useThemeStore } from '../../stores'
import type { TenantType } from '../../types/models'

// Multi mode: guests preview any enabled theme; signed-in users and standalone apps get a fixed one
const { t } = useI18n()
const themeStore = useThemeStore()
</script>

<style scoped>
.theme-switcher {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.6rem;
  border-radius: 999px;
  background: var(--surface-elevated);
  border: 1px solid var(--border);
  color: var(--text);
}

.theme-switcher .label {
  font-size: 0.8rem;
  color: var(--muted);
}

.theme-switcher select {
  background: transparent;
  color: var(--text);
  border: none;
  outline: none;
  font: inherit;
}
</style>

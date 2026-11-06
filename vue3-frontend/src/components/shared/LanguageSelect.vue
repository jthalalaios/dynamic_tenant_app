<template>
  <div class="language-select" ref="containerRef">
    <button class="trigger" @click="open = !open" :aria-label="t('common.language')" :title="t('common.language')" aria-haspopup="true" :aria-expanded="open">
      <i class="pi pi-globe"></i>
    </button>

    <transition name="fade">
      <div v-if="open" class="menu" role="menu">
        <button
          v-for="lang in SUPPORTED_LOCALES"
          :key="lang.code"
          class="option"
          :class="{ active: prefs.locale === lang.code }"
          role="menuitemradio"
          :aria-checked="prefs.locale === lang.code"
          @click="select(lang.code)"
        >
          <span class="flag" aria-hidden="true">{{ lang.flag }}</span>
          <span>{{ lang.label }}</span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, type LocaleCode } from '../../helpers/language'
import { usePreferencesStore } from '../../stores'
import { usePreferenceActions } from '../../composables/usePreferenceActions'
import { useClickOutside } from '../../composables/useClickOutside'

const { t } = useI18n()
const prefs = usePreferencesStore()
const { changeLocale } = usePreferenceActions()
const open = ref(false)
const containerRef = ref<HTMLElement | null>(null)

useClickOutside(containerRef, () => (open.value = false))

async function select(code: LocaleCode) {
  open.value = false
  await changeLocale(code)
}
</script>

<style scoped>
.language-select {
  position: relative;
}

.trigger {
  color: var(--text);
  background: var(--surface-elevated);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.45rem 0.6rem;
  cursor: pointer;
}

.menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 0.4rem;
  min-width: 140px;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
  z-index: 20;
}

.option {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  border: none;
  background: transparent;
  color: var(--text);
  text-align: left;
  padding: 0.5rem 0.6rem;
  border-radius: 8px;
  cursor: pointer;
}

.flag {
  font-size: 1rem;
  line-height: 1;
}

.option:hover,
.option.active {
  background: var(--surface-elevated);
  color: var(--primary);
}
</style>

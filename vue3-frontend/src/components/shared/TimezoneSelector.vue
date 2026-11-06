<template>
  <Select
    :modelValue="prefs.timezone"
    :options="options"
    optionLabel="label"
    optionValue="value"
    filter
    :filterPlaceholder="t('common.search')"
    :aria-label="t('common.timezone')"
    :size="compact ? 'small' : undefined"
    :class="['timezone-select', { 'timezone-select--compact': compact }]"
    @update:modelValue="onChange"
  />
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Select from 'primevue/select'
import { listTimezones, timezoneOffsetLabel } from '../../helpers/timezone'
import { usePreferencesStore } from '../../stores'
import { usePreferenceActions } from '../../composables/usePreferenceActions'

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const { t } = useI18n()
const prefs = usePreferencesStore()
const { changeTimezone } = usePreferenceActions()

const options = computed(() => {
  const now = new Date()
  return listTimezones().map((tz) => ({ value: tz, label: `${tz.replace(/_/g, ' ')} (${timezoneOffsetLabel(tz, now)})` }))
})

function onChange(tz: string) {
  if (tz && tz !== prefs.timezone) void changeTimezone(tz)
}
</script>

<style scoped>
.timezone-select {
  min-width: 14rem;
}
.timezone-select--compact {
  max-width: 13rem;
  min-width: 0;
}
</style>

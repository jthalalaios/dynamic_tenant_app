<template>
  <Tag :value="t(`status.${kind}.${snakeToCamel(status)}`)" :severity="severity" rounded />
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import { snakeToCamel } from '../../helpers/text'

const props = defineProps<{ kind: 'appointment' | 'order'; status: string }>()
const { t } = useI18n()

const SEVERITY: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
  scheduled: 'warn',
  checked_in: 'info',
  in_progress: 'contrast',
  completed: 'success',
  cancelled: 'danger',
  no_show: 'secondary',
  pending: 'warn',
  paid: 'info',
  shipped: 'contrast',
  delivered: 'success'
}

const severity = computed(() => SEVERITY[props.status] ?? 'secondary')
</script>

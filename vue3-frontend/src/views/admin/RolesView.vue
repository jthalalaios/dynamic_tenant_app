<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.administration')" :title="t('admin.roles.title')" :subtitle="t('admin.roles.subtitle')">
      <template #actions>
        <Button v-if="$can('roles.create')" :label="t('admin.roles.new')" icon="pi pi-plus" @click="startCreate" />
      </template>
    </PageHeader>

    <div class="roles-layout">
      <BaseCard padding="sm" class="roles-list">
        <Skeleton v-if="loading" height="12rem" />
        <ul v-else>
          <li v-for="role in roles" :key="role.id">
            <button :class="['role-item', { 'role-item--active': selected?.id === role.id }]" @click="select(role)">
              <span class="role-item__name">
                {{ role.name }}
                <i v-if="role.is_system" class="pi pi-lock muted" :title="t('admin.roles.system')" aria-hidden="true"></i>
              </span>
              <span class="role-item__meta">
                <Tag v-if="role.is_admin" :value="t('roles.admin')" severity="contrast" rounded />
                <span class="muted">{{ t('admin.roles.members', { count: role.users_count ?? 0 }) }}</span>
              </span>
            </button>
          </li>
        </ul>
      </BaseCard>

      <BaseCard v-if="editorOpen" padding="lg">
        <form class="role-form" @submit.prevent="save" novalidate>
          <div class="dialog-form">
            <div class="field">
              <label for="r_name">{{ t('auth.name') }}</label>
              <InputText id="r_name" v-model="form.name" :disabled="readOnly" :invalid="!!errors.first('name')" fluid />
              <small v-if="errors.first('name')" class="field-error">{{ errors.first('name') }}</small>
            </div>
            <div class="field">
              <label for="r_desc">{{ t('admin.roles.description') }}</label>
              <InputText id="r_desc" v-model="form.description" :disabled="readOnly" fluid />
            </div>
            <div class="field field--full toggle-field">
              <ToggleSwitch inputId="r_admin" v-model="form.is_admin" :disabled="readOnly || !!selected?.is_system" />
              <label for="r_admin">{{ t('admin.roles.isAdmin') }}</label>
              <small class="muted">{{ t('admin.roles.isAdminHint') }}</small>
            </div>
          </div>

          <h3 class="matrix-title">{{ t('admin.roles.permissions') }}</h3>
          <Message v-if="form.is_admin" severity="info" :closable="false">{{ t('admin.roles.adminHasAll') }}</Message>
          <div v-else class="matrix">
            <fieldset v-for="group in permissionGroups" :key="group.group" class="matrix-group">
              <legend>
                <Checkbox
                  :inputId="`group-${group.group}`"
                  binary
                  :modelValue="groupState(group).all"
                  :indeterminate="groupState(group).some && !groupState(group).all"
                  :disabled="readOnly"
                  @update:modelValue="toggleGroup(group, $event)"
                />
                <label :for="`group-${group.group}`">{{ t(`permissionGroups.${group.group}`) }}</label>
              </legend>
              <div v-for="permission in group.permissions" :key="permission.name" class="matrix-item">
                <Checkbox :inputId="permission.name" v-model="form.permissions" :value="permission.name" :disabled="readOnly" />
                <label :for="permission.name">
                  <span>{{ permission.description }}</span>
                  <code class="muted">{{ permission.name }}</code>
                </label>
              </div>
            </fieldset>
          </div>
          <small v-if="permissionsError" class="field-error">{{ permissionsError }}</small>

          <div class="role-form__actions">
            <Button
              v-if="selected && !selected.is_system && $can('roles.delete')"
              :label="t('common.delete')"
              icon="pi pi-trash"
              severity="danger"
              text
              @click="confirmDelete(selected)"
            />
            <span class="spacer"></span>
            <Button v-if="!readOnly" :label="t('common.save')" type="submit" :loading="saving" />
          </div>
        </form>
      </BaseCard>
      <BaseCard v-else padding="lg">
        <p class="muted">{{ t('admin.roles.pickOne') }}</p>
      </BaseCard>
    </div>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import { permissionsApi, rolesApi } from '../../api'
import { useAccessStore, useAuthStore } from '../../stores'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import type { PermissionGroup, Role } from '../../types/models'

const { t } = useI18n()
const confirm = useConfirm()
const notify = useNotify()
const errors = useFormErrors()
const auth = useAuthStore()
const access = useAccessStore()

const roles = ref<Role[]>([])
const permissionGroups = ref<PermissionGroup[]>([])
const loading = ref(true)
const saving = ref(false)
const selected = ref<Role | null>(null)
const creating = ref(false)
const form = reactive({ name: '', description: '', is_admin: false, permissions: [] as string[] })

const editorOpen = computed(() => creating.value || selected.value !== null)
const readOnly = computed(() => (creating.value ? !access.can('roles.create') : !access.can('roles.update')))
const permissionsError = computed(() => Object.entries(errors.errors.value).find(([k]) => k.startsWith('permissions'))?.[1]?.[0])

function groupState(group: PermissionGroup) {
  const names = group.permissions.map((p) => p.name)
  const granted = names.filter((n) => form.permissions.includes(n)).length
  return { all: granted === names.length, some: granted > 0 }
}

function toggleGroup(group: PermissionGroup, checked: boolean) {
  const names = group.permissions.map((p) => p.name)
  form.permissions = checked ? [...new Set([...form.permissions, ...names])] : form.permissions.filter((p) => !names.includes(p))
}

function select(role: Role) {
  creating.value = false
  selected.value = role
  errors.clear()
  Object.assign(form, { name: role.name, description: role.description ?? '', is_admin: role.is_admin, permissions: [...(role.permissions ?? [])] })
}

function startCreate() {
  creating.value = true
  selected.value = null
  errors.clear()
  Object.assign(form, { name: '', description: '', is_admin: false, permissions: [] })
}

async function loadRoles() {
  roles.value = await rolesApi.all()
  if (selected.value) {
    const fresh = roles.value.find((r) => r.id === selected.value?.id)
    fresh ? select(fresh) : (selected.value = null)
  }
}

async function save() {
  saving.value = true
  errors.clear()
  const payload = {
    name: form.name,
    description: form.description || null,
    // System roles keep their admin flag; sending it unchanged is a no-op either way
    is_admin: form.is_admin,
    permissions: form.is_admin ? [] : form.permissions
  }
  try {
    const role = selected.value ? await rolesApi.update(selected.value.id, payload) : await rolesApi.create(payload)
    creating.value = false
    selected.value = role
    await loadRoles()
    // The signed-in user's own grants may have changed
    await auth.refreshSession()
    notify.success(t('common.saved'))
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}

function confirmDelete(role: Role) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('admin.roles.confirmDelete', { name: role.name, count: role.users_count ?? 0 }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await rolesApi.remove(role.id)
        selected.value = null
        await loadRoles()
        await auth.refreshSession()
        notify.success(t('common.deleted'))
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

onMounted(async () => {
  try {
    const [, groups] = await Promise.all([loadRoles(), permissionsApi.grouped()])
    permissionGroups.value = groups
  } catch (err) {
    notify.error(err)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.roles-layout {
  display: grid;
  grid-template-columns: minmax(220px, 300px) 1fr;
  gap: 1rem;
  align-items: start;
}
.roles-list ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.25rem;
}
.role-item {
  width: 100%;
  display: grid;
  gap: 0.3rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid transparent;
  border-radius: 12px;
  background: transparent;
  color: var(--text);
  text-align: left;
  font: inherit;
  cursor: pointer;
}
.role-item:hover {
  background: var(--surface-elevated);
}
.role-item--active {
  border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
  background: var(--primary-soft);
}
.role-item__name {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
}
.role-item__meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}
.toggle-field {
  display: flex !important;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}
.matrix-title {
  font-size: 1rem;
  margin: 1.5rem 0 0.75rem;
}
.matrix {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 0.9rem;
}
.matrix-group {
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 0.6rem 0.9rem 0.8rem;
  margin: 0;
}
.matrix-group legend {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0 0.4rem;
  font-weight: 700;
}
.matrix-item {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  padding: 0.35rem 0;
}
.matrix-item label {
  display: grid;
  gap: 0.1rem;
  font-size: 0.92rem;
  cursor: pointer;
}
.matrix-item code {
  font-size: 0.75rem;
}
.role-form__actions {
  display: flex;
  align-items: center;
  margin-top: 1.25rem;
}
.spacer {
  flex: 1;
}
@media (max-width: 860px) {
  .roles-layout {
    grid-template-columns: 1fr;
  }
}
</style>

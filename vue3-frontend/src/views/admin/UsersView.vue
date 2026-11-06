<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.administration')" :title="t('admin.users.title')" :subtitle="t('admin.users.subtitle')">
      <template #actions>
        <Button v-if="$can('users.create')" :label="t('admin.users.new')" icon="pi pi-user-plus" @click="openCreate" />
      </template>
    </PageHeader>

    <BaseCard>
      <div class="table-toolbar">
        <div class="table-toolbar__filters">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="list.filters.search" :placeholder="t('admin.users.search')" @input="debouncedSearch" />
          </IconField>
          <Select v-model="list.filters.role_id" :options="roles" optionLabel="name" optionValue="id" showClear :placeholder="t('admin.users.anyRole')" @change="list.search()" />
        </div>
      </div>

      <DataTable
        lazy
        paginator
        :value="list.items.value"
        :totalRecords="list.total.value"
        :rows="list.rows.value"
        :first="(list.page.value - 1) * list.rows.value"
        :loading="list.loading.value"
        :rowsPerPageOptions="[15, 30, 50]"
        dataKey="id"
        stripedRows
        @page="list.onPage"
      >
        <template #empty>{{ t('common.noResults') }}</template>
        <Column :header="t('auth.name')">
          <template #body="{ data }">
            <div class="user-cell">
              <strong>{{ data.name }}</strong>
              <span class="muted">{{ data.email }}</span>
            </div>
          </template>
        </Column>
        <Column :header="t('nav.roles')">
          <template #body="{ data }">
            <div class="tag-list">
              <Tag v-for="role in data.roles" :key="role.id" :value="role.name" :severity="role.is_admin ? 'contrast' : 'secondary'" rounded />
            </div>
          </template>
        </Column>
        <Column :header="t('common.status')">
          <template #body="{ data }">
            <Tag :value="data.is_active ? t('common.active') : t('common.inactive')" :severity="data.is_active ? 'success' : 'danger'" rounded />
          </template>
        </Column>
        <Column :header="t('admin.users.lastLogin')">
          <template #body="{ data }">{{ fmt.dateTime(data.last_login_at) }}</template>
        </Column>
        <Column :header="t('common.actions')" headerClass="row-actions">
          <template #body="{ data }">
            <div class="row-actions">
              <Button v-if="$can('users.update')" icon="pi pi-pencil" text rounded size="small" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button
                v-if="$can('users.delete') && data.id !== user.user?.id"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                :aria-label="t('common.delete')"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </BaseCard>

    <Dialog v-model:visible="dialogOpen" modal :header="editing ? t('admin.users.edit') : t('admin.users.new')" :style="{ width: 'min(640px, 95vw)' }">
      <form id="user-form" class="dialog-form" @submit.prevent="save" novalidate>
        <div class="field">
          <label for="u_name">{{ t('auth.name') }}</label>
          <InputText id="u_name" v-model="form.name" :invalid="!!errors.first('name')" fluid />
          <small v-if="errors.first('name')" class="field-error">{{ errors.first('name') }}</small>
        </div>
        <div class="field">
          <label for="u_email">{{ t('auth.email') }}</label>
          <InputText id="u_email" v-model="form.email" type="email" :invalid="!!errors.first('email')" fluid />
          <small v-if="errors.first('email')" class="field-error">{{ errors.first('email') }}</small>
        </div>
        <div class="field field--full">
          <label for="u_password">{{ editing ? t('admin.users.newPasswordOptional') : t('auth.password') }}</label>
          <Password inputId="u_password" v-model="form.password" toggleMask autocomplete="new-password" :invalid="!!errors.first('password')" fluid />
          <small v-if="errors.first('password')" class="field-error">{{ errors.first('password') }}</small>
        </div>
        <div class="field field--full">
          <label for="u_roles">{{ t('nav.roles') }}</label>
          <MultiSelect inputId="u_roles" v-model="form.role_ids" :options="roles" optionLabel="name" optionValue="id" display="chip" :invalid="!!roleError" fluid />
          <small v-if="roleError" class="field-error">{{ roleError }}</small>
          <small class="muted">{{ t('admin.users.rolesHint') }}</small>
        </div>
        <div v-if="editing" class="field field--full toggle-field">
          <ToggleSwitch inputId="u_active" v-model="form.is_active" :disabled="editing.id === user.user?.id" />
          <label for="u_active">{{ t('common.active') }}</label>
        </div>
      </form>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="dialogOpen = false" />
        <Button :label="t('common.save')" type="submit" form="user-form" :loading="saving" />
      </template>
    </Dialog>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import { rolesApi, usersApi } from '../../api'
import { useAuthStore, useUserStore } from '../../stores'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import { useFormatters } from '../../composables/useFormatters'
import type { Role, TeamMember } from '../../types/models'

const { t } = useI18n()
const confirm = useConfirm()
const notify = useNotify()
const errors = useFormErrors()
const fmt = useFormatters()
const auth = useAuthStore()
const user = useUserStore()
const list = usePaginatedList(usersApi.list, { search: '', role_id: null as number | null })
const roles = ref<Role[]>([])

let searchTimer: ReturnType<typeof setTimeout> | undefined
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void list.search(), 300)
}
onUnmounted(() => clearTimeout(searchTimer))

const dialogOpen = ref(false)
const saving = ref(false)
const editing = ref<TeamMember | null>(null)
const emptyForm = () => ({ name: '', email: '', password: '', role_ids: [] as number[], is_active: true })
const form = reactive(emptyForm())
const roleError = computed(() => errors.first('role_ids') ?? Object.entries(errors.errors.value).find(([k]) => k.startsWith('role_ids.'))?.[1]?.[0])

function openCreate() {
  editing.value = null
  errors.clear()
  Object.assign(form, emptyForm())
  dialogOpen.value = true
}

function openEdit(member: TeamMember) {
  editing.value = member
  errors.clear()
  Object.assign(form, {
    name: member.name,
    email: member.email,
    password: '',
    role_ids: member.roles?.map((r) => r.id) ?? [],
    is_active: member.is_active
  })
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  errors.clear()
  try {
    if (editing.value) {
      await usersApi.update(editing.value.id, {
        name: form.name,
        email: form.email,
        password: form.password || null,
        role_ids: form.role_ids,
        is_active: form.is_active
      })
      // Editing yourself may change your own permissions
      if (editing.value.id === user.user?.id) await auth.refreshSession()
    } else {
      await usersApi.create({ name: form.name, email: form.email, password: form.password, role_ids: form.role_ids })
    }
    notify.success(t('common.saved'))
    dialogOpen.value = false
    await list.load()
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}

function confirmDelete(member: TeamMember) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('admin.users.confirmDelete', { name: member.name }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await usersApi.remove(member.id)
        notify.success(t('common.deleted'))
        await list.load()
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

onMounted(async () => {
  await list.load()
  try {
    roles.value = await rolesApi.all()
  } catch (err) {
    notify.error(err)
  }
})
</script>

<style scoped>
.user-cell {
  display: grid;
}
.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}
.toggle-field {
  display: flex !important;
  align-items: center;
  gap: 0.6rem;
}
</style>

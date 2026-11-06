import { reactive, ref, shallowRef, type Ref } from 'vue'
import type { DataTablePageEvent } from 'primevue/datatable'
import type { ListParams, Paginated } from '../types/api'
import { useNotify } from './useNotify'

/**
 * State for a server-paginated, filterable table (PrimeVue DataTable in lazy mode).
 *
 *   const list = usePaginatedList(patientsApi.list, { search: '' })
 *   <DataTable lazy :value="list.items.value" :totalRecords="list.total.value" @page="list.onPage" />
 */
export function usePaginatedList<T, F extends ListParams = ListParams>(
  fetcher: (params: ListParams) => Promise<Paginated<T>>,
  initialFilters: F = {} as F,
  perPage = 15
) {
  const notify = useNotify()
  const items = shallowRef<T[]>([]) as Ref<T[]>
  const total = ref(0)
  const loading = ref(false)
  const page = ref(1)
  const rows = ref(perPage)
  const filters = reactive({ ...initialFilters }) as F
  // Ignore responses from superseded requests (fast typing in a search box)
  let requestId = 0

  async function load(): Promise<void> {
    const current = ++requestId
    loading.value = true
    try {
      const response = await fetcher({ ...filters, page: page.value, per_page: rows.value })
      if (current !== requestId) return
      items.value = response.data
      total.value = response.meta.total
    } catch (err) {
      if (current === requestId) notify.error(err)
    } finally {
      if (current === requestId) loading.value = false
    }
  }

  function onPage(event: DataTablePageEvent): void {
    page.value = event.page + 1
    rows.value = event.rows
    void load()
  }

  /** Reset to page 1 after a filter change */
  function search(): Promise<void> {
    page.value = 1
    return load()
  }

  return { items, total, loading, page, rows, filters, load, onPage, search }
}

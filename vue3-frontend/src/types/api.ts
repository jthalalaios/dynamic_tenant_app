/** Single resource envelope returned by Laravel API resources */
export interface Resource<T> {
  data: T
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/** Laravel paginated resource collection */
export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
}

export type QueryValue = string | number | boolean | null | undefined

export interface ListParams {
  page?: number
  per_page?: number
  search?: string
  [filter: string]: QueryValue
}

export type ValidationErrors = Record<string, string[]>

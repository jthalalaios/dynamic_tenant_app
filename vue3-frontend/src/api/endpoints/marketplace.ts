import { http, resource } from '../http'
import type { Paginated, ListParams, Resource } from '../../types/api'
import type { MarketplaceDashboard, Order, OrderStatus, StoreProduct, TopProduct } from '../../types/models'

export type ProductPayload = Partial<Omit<StoreProduct, 'id' | 'created_at'>>

export interface OrderPayload {
  customer_name: string
  customer_email?: string | null
  items: { product_id: number; quantity: number }[]
}

export const productsApi = resource<StoreProduct, ProductPayload>('/marketplace/products')

export const ordersApi = {
  list: (params?: ListParams) => http.get<Paginated<Order>>('/marketplace/orders', params),
  show: (id: number) => http.get<Resource<Order>>(`/marketplace/orders/${id}`).then((r) => r.data),
  create: (payload: OrderPayload) => http.post<Resource<Order>>('/marketplace/orders', payload).then((r) => r.data),
  setStatus: (id: number, status: OrderStatus) =>
    http.patch<Resource<Order>>(`/marketplace/orders/${id}/status`, { status }).then((r) => r.data)
}

export const marketplaceApi = {
  dashboard: () => http.get<Resource<MarketplaceDashboard>>('/marketplace/dashboard').then((r) => r.data),
  topProducts: () => http.get<{ data: TopProduct[] }>('/marketplace/insights/top-products').then((r) => r.data)
}

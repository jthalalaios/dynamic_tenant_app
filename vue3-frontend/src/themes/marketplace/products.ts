import type { Product } from '../../types/app'

const sampleProducts: Product[] = [
  { id: 1, name: 'Aurora Headphones', category: 'audio', icon: 'pi pi-headphones', price: 129, stock: 24, rating: 5 },
  { id: 2, name: 'Pulse Smartwatch', category: 'wearables', icon: 'pi pi-clock', price: 199, stock: 6, rating: 4 },
  { id: 3, name: 'Nimbus Desk Lamp', category: 'home', icon: 'pi pi-sun', price: 49, stock: 41, rating: 4 },
  { id: 4, name: 'Voyager Backpack', category: 'accessories', icon: 'pi pi-briefcase', price: 89, stock: 0, rating: 5 }
]

/** Fresh copy so each view can mutate its own catalog */
export function getSampleProducts(): Product[] {
  return sampleProducts.map((p) => ({ ...p }))
}

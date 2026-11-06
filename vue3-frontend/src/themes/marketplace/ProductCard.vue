<template>
  <BaseCard class="product-card" padding="none" interactive>
    <div class="product-card__media">
      <i :class="product.icon" aria-hidden="true"></i>
      <Tag class="product-card__stock" :value="stockLabel" :severity="stockSeverity" rounded />
    </div>

    <div class="product-card__content">
      <span class="product-card__category">{{ t(`marketplace.categories.${product.category}`) }}</span>
      <h3 class="product-card__name">{{ product.name }}</h3>
      <div class="product-card__rating" :aria-label="t('cards.rating', { rating: product.rating })">
        <i
          v-for="star in 5"
          :key="star"
          :class="star <= product.rating ? 'pi pi-star-fill' : 'pi pi-star'"
          aria-hidden="true"
        ></i>
      </div>
    </div>

    <template #footer>
      <span class="product-card__price">{{ price }}</span>
      <Button
        class="product-card__add"
        :label="t('cards.addToCart')"
        icon="pi pi-shopping-cart"
        size="small"
        :disabled="product.stock === 0"
        @click="emit('add', product)"
      />
    </template>
  </BaseCard>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import type { Product } from '../../types/app'

const LOW_STOCK_THRESHOLD = 10

const props = defineProps<{ product: Product }>()
const emit = defineEmits<{ add: [product: Product] }>()

const { t, locale } = useI18n()

const price = computed(() =>
  new Intl.NumberFormat(locale.value === 'el' ? 'el-GR' : 'en-US', { style: 'currency', currency: 'USD' })
    .format(props.product.price)
)

const stockLabel = computed(() => {
  if (props.product.stock === 0) return t('cards.soldOut')
  if (props.product.stock < LOW_STOCK_THRESHOLD) return t('cards.lowStock', { count: props.product.stock })
  return t('cards.inStock')
})

const stockSeverity = computed(() => {
  if (props.product.stock === 0) return 'danger'
  if (props.product.stock < LOW_STOCK_THRESHOLD) return 'warn'
  return 'success'
})
</script>

<style scoped>
.product-card__media {
  position: relative;
  display: grid;
  place-items: center;
  height: 140px;
  color: var(--primary);
  background:
    radial-gradient(circle at 75% 20%, var(--accent-soft), transparent 55%),
    linear-gradient(135deg, var(--surface-elevated), var(--primary-soft));
}
/* primeicons sets its own font-size on .pi, so size the icon directly */
.product-card__media > .pi {
  font-size: 2.75rem;
  filter: drop-shadow(0 6px 12px color-mix(in srgb, var(--primary) 25%, transparent));
}
.product-card__stock {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
}
.product-card__content {
  padding: 1rem 1.1rem;
}
.product-card__category {
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.product-card__name {
  font-size: 1.05rem;
  margin: 0.25rem 0 0.4rem;
}
.product-card__rating {
  display: flex;
  gap: 0.15rem;
  color: var(--accent);
  font-size: 0.85rem;
}
.product-card__price {
  font-size: 1.15rem;
  font-weight: 800;
}
.product-card__add {
  margin-left: auto;
}
</style>

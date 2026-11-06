<template>
  <component
    :is="as"
    :class="['base-card', `base-card--${padding}`, { 'base-card--interactive': interactive, 'base-card--accent': accent }]"
  >
    <header v-if="$slots.header" class="base-card__header">
      <slot name="header" />
    </header>
    <div class="base-card__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="base-card__footer">
      <slot name="footer" />
    </footer>
  </component>
</template>

<script lang="ts" setup>
withDefaults(defineProps<{
  as?: string
  padding?: 'none' | 'sm' | 'md' | 'lg'
  /** Lift and glow on hover, for cards the user can act on */
  interactive?: boolean
  /** Thin primary→accent bar along the top edge */
  accent?: boolean
}>(), {
  as: 'article',
  padding: 'md',
  interactive: false,
  accent: false
})
</script>

<style scoped>
.base-card {
  position: relative;
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  transition: transform 200ms ease, box-shadow 200ms ease, border-color 200ms ease, background-color 180ms ease;
}

.base-card--accent::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: linear-gradient(90deg, var(--primary), var(--accent));
}

.base-card--interactive:hover,
.base-card--interactive:focus-within {
  transform: translateY(-3px);
  box-shadow: var(--card-shadow-hover);
  border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
}

.base-card__body {
  flex: 1;
}

.base-card--none .base-card__body { padding: 0; }
.base-card--sm .base-card__body { padding: 0.85rem; }
.base-card--md .base-card__body { padding: 1.25rem; }
.base-card--lg .base-card__body { padding: 1.75rem; }

.base-card__header,
.base-card__footer {
  padding: 0.85rem 1.25rem;
}
.base-card__header {
  border-bottom: 1px solid var(--border);
}
.base-card__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  border-top: 1px solid var(--border);
  background: var(--surface-elevated);
}

@media (max-width: 640px) {
  .base-card--lg .base-card__body { padding: 1.25rem; }
}
</style>

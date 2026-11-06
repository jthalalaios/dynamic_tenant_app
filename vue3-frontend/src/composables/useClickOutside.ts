import { onMounted, onUnmounted, type Ref } from 'vue'

/** Calls `handler` on clicks outside the referenced element (closing menus, popovers) */
export function useClickOutside(target: Ref<HTMLElement | null>, handler: () => void): void {
  const listener = (event: MouseEvent) => {
    if (target.value && !target.value.contains(event.target as Node)) handler()
  }

  onMounted(() => document.addEventListener('click', listener))
  onUnmounted(() => document.removeEventListener('click', listener))
}

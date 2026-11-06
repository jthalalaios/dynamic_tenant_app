export function splitTextAnimations() {
  // Simple reveal animation for elements with data-split attribute
  const els = document.querySelectorAll<HTMLElement>('[data-split]')
  els.forEach((el, idx) => {
    el.style.opacity = '0'
    el.style.transform = 'translateY(8px)'
    setTimeout(() => {
      el.style.transition = 'opacity .6s ease, transform .6s ease'
      el.style.opacity = '1'
      el.style.transform = 'none'
    }, 80 * idx)
  })
}

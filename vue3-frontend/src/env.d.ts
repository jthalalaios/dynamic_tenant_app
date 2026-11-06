/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL?: string
  readonly VITE_PORT?: string
  /** Standalone theme for the whole build: doctor | marketplace */
  readonly VITE_APP_THEME?: string
  /** Multi mode: comma separated themes to offer (default: all) */
  readonly VITE_APP_THEMES?: string
  /** Standalone theme per hostname: "clinic.localhost:5173=doctor,market.localhost:5173=marketplace" */
  readonly VITE_THEME_HOSTS?: string
  /** Laravel Reverb (live chat). Without a key the chat still works, without live updates */
  readonly VITE_REVERB_APP_KEY?: string
  /** Websocket host; defaults to the page's hostname */
  readonly VITE_REVERB_HOST?: string
  readonly VITE_REVERB_PORT?: string
  /** http | https */
  readonly VITE_REVERB_SCHEME?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

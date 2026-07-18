import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  // The MIT PDF.js reader is intentionally isolated behind a dynamic import;
  // it does not affect the 262 kB application entry chunk.
  build: { chunkSizeWarningLimit: 2600 },
})

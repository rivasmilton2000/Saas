import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
  root: resolve(__dirname), plugins: [react()],
  build: { outDir: resolve(__dirname, '../../build/frontend'), emptyOutDir: true },
  server: { host: '127.0.0.1', port: 5173, strictPort: true,
    proxy: { '^/api(?:/|$)': 'http://127.0.0.1:3000', '/public-assets': 'http://127.0.0.1:3000', '/auth-assets': 'http://127.0.0.1:3000', '/dashboard-assets': 'http://127.0.0.1:3000', '/uploads/profiles': 'http://127.0.0.1:3000' } },
});

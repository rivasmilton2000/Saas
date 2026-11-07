import {defineConfig} from '@playwright/test';
export default defineConfig({
  testDir:'./tests/browser',workers:1,timeout:30000,
  use:{baseURL:'http://127.0.0.1:3101',headless:true,screenshot:'only-on-failure'},
  globalTeardown:'./tests/browser-teardown.ts',
  webServer:{command:'npx.cmd tsx --tsconfig src/backend/tsconfig.json tests/browser-server.ts',url:'http://127.0.0.1:3101/api/health',reuseExistingServer:false,timeout:30000},
});

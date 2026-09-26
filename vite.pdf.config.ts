import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    lib: {
      entry: path.resolve(__dirname, 'src/lib/pdf/index.ts'),
      name: 'VellisysPdf',
      formats: ['iife'],
      fileName: () => 'vellisys-pdf.js',
    },
    outDir: path.resolve(__dirname, 'assets/js/pdf'),
    emptyOutDir: true,
    sourcemap: false,
    minify: true,
    rollupOptions: {
      output: {
        exports: 'named',
        inlineDynamicImports: true,
        assetFileNames: 'vellisys-pdf.[ext]',
      },
    },
  },
});

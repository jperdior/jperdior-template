import type { NextConfig } from 'next';
import path from 'node:path';

const config: NextConfig = {
  reactStrictMode: true,
  transpilePackages: ['@jperdior/ui-react', '@jperdior/api-client-ts'],
  output: 'standalone',
  outputFileTracingRoot: path.join(__dirname, '../..'),
};

export default config;

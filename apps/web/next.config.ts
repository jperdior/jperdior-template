import type { NextConfig } from 'next';
import path from 'node:path';

const config: NextConfig = {
  reactStrictMode: true,
  transpilePackages: ['@jperdior/ui-react', '@jperdior/api-client-ts'],
  experimental: {
    typedRoutes: true,
  },
  output: 'standalone',
  // Trace workspace symlinks so the standalone output bundles `packages/*`.
  outputFileTracingRoot: path.join(__dirname, '../..'),
};

export default config;

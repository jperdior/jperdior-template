import type { NextConfig } from 'next';
import path from 'node:path';

const config: NextConfig = {
  reactStrictMode: true,
  experimental: {
    turbopackFileSystemCacheForDev: false,
  },
  transpilePackages: ['@jperdior/ui-react', '@jperdior/api-client-ts', '@jperdior/auth-server'],
  typedRoutes: true,
  output: 'standalone',
  // Trace workspace symlinks so the standalone output bundles `packages/*`.
  outputFileTracingRoot: path.join(__dirname, '../..'),
};

export default config;

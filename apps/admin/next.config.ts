import type { NextConfig } from 'next';

const config: NextConfig = {
  reactStrictMode: true,
  transpilePackages: ['@jperdior/ui-react', '@jperdior/api-client-ts'],
  output: 'standalone',
};

export default config;

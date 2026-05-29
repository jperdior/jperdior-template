import type { Config } from 'tailwindcss';
// @ts-expect-error JS preset
import preset from '@jperdior/ui-react/tailwind-preset';

const config: Config = {
  presets: [preset],
  content: [
    './src/**/*.{ts,tsx}',
    '../../packages/ui-react/src/**/*.{ts,tsx}',
  ],
};

export default config;

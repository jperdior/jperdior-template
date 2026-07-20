import { createNavigation } from 'next-intl/navigation';
import { routing } from './routing';

// Locale-aware navigation. Import `Link`, `redirect`, `usePathname`, `useRouter`,
// `getPathname` from HERE (not from `next/link` / `next/navigation`) for any in-app
// route so the current locale prefix (`/es`) is preserved automatically.
export const { Link, redirect, usePathname, useRouter, getPathname } = createNavigation(routing);

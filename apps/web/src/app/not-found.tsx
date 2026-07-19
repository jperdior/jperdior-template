import './globals.css';

// Global 404 for requests that never match the `[locale]` segment. No root layout applies
// here, so this file renders its own <html>/<body>. Localized 404s (inside the app) use
// app/[locale]/not-found.tsx instead.
export default function GlobalNotFound() {
  return (
    <html lang="en">
      <body className="min-h-screen bg-background text-foreground antialiased">
        <main className="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-4 px-6 py-16 text-center">
          <h1 className="text-3xl font-semibold">404</h1>
          <p className="text-muted-foreground">This page doesn&apos;t exist.</p>
          {/* eslint-disable-next-line @next/next/no-html-link-for-pages -- global fallback outside any locale context; no locale-aware Link available here */}
          <a
            href="/"
            className="inline-flex h-10 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
          >
            Back home
          </a>
        </main>
      </body>
    </html>
  );
}

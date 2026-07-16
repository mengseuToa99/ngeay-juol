# Task: Implement PWA for ngeay juol (RentWise)

## Project Context

- **App name:** ងាយជួល (ngeay juol) — Cambodian landlord property rental management system
- **Framework:** Laravel 11 + Filament v3 (admin panel) + Livewire
- **Asset pipeline:** Vite + Tailwind CSS v4 (`@tailwindcss/vite`)
- **Language:** Khmer (km) + English (en), bilingual UI
- **Dev server:** `php artisan serve --port=8001`
- **Project root:** `/home/tms/project/rentwise`

---

## User Panels (3 distinct surfaces)

| Panel | URL prefix | Layout file | Provider |
|---|---|---|---|
| Landlord (Filament) | `/landlord/*` | Injected via `LandlordPanelProvider.php` | `app/Providers/Filament/LandlordPanelProvider.php` |
| Tenant portal | `/portal/*` | `resources/views/portal/layout.blade.php` | — |
| Welcome / marketing | `/` | `resources/views/welcome.blade.php` | — |
| Auth pages | `/login` | Filament default layout | — |

---

## Current PWA State (What Already Exists)

| File | Path | Status |
|---|---|---|
| Web App Manifest | `public/manifest.json` | ✅ Exists |
| Service Worker | `public/sw.js` | ✅ Exists (v1, minimal) |
| App logo SVG | `public/Khmer House Key.svg` | ✅ Exists |
| App logo PNG | `public/Khmer House Key.png` | ✅ Exists (raw, not sized) |
| Manifest link + SW registration | `LandlordPanelProvider` render hooks | ✅ Landlord panel only |
| Manifest on welcome / portal / login | — | ❌ Missing |
| SW on welcome / portal / login | — | ❌ Missing |
| Proper 192px icon | — | ❌ Missing |
| Proper 512px icon | — | ❌ Missing |
| Offline fallback page | Inline string in sw.js | ⚠️ Needs own file |
| Install prompt UI | — | ❌ Missing |

### Current `manifest.json` problems
- `"scope": "/landlord"` — too narrow, install prompt won't fire on login/welcome
- Both 192px and 512px icons point to the same raw PNG (fails Lighthouse)
- No `screenshots` field (required by modern Chrome install sheet)

### Current `sw.js` problems
- Offline fallback is an inline HTML string inside JS (hard to maintain)
- Google Fonts are never cached
- No pre-caching of shell routes
- Cache name is `ngeay-juol-shell-v1` — must be bumped on update

---

## Phase 1 — Icons

Create directory: `public/icons/`

Generate 3 icon files using `public/Khmer House Key.svg` or `.png` as source:

- `public/icons/icon-192.png` — exactly 192×192px, PNG
- `public/icons/icon-512.png` — exactly 512×512px, PNG
- `public/icons/icon-maskable-512.png` — 512×512px PNG with ~20% safe-zone padding on all sides (Android adaptive icon)

If regenerating the icon image, use: emerald green gradient background (#059669 → #047857), white centered icon, flat modern style, no text.

---

## Phase 2 — Fix `public/manifest.json`

Replace entirely with:

```json
{
    "name": "ងាយជួល — ngeay juol",
    "short_name": "ងាយជួល",
    "description": "Landlord property management — simple daily tool for rental tracking in Cambodia.",
    "start_url": "/login",
    "scope": "/",
    "display": "standalone",
    "display_override": ["window-controls-overlay", "standalone"],
    "orientation": "portrait-primary",
    "background_color": "#f0fdf4",
    "theme_color": "#059669",
    "lang": "km",
    "dir": "ltr",
    "categories": ["productivity", "business", "finance"],
    "icons": [
        { "src": "/favicon.ico", "sizes": "48x48", "type": "image/x-icon" },
        { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
        { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
        { "src": "/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
    ],
    "shortcuts": [
        { "name": "ចេញវិក្កយបត្រ", "short_name": "Billing", "url": "/landlord/monthly-billing", "icons": [{ "src": "/icons/icon-192.png", "sizes": "192x192" }] },
        { "name": "វិក្កយបត្រ", "short_name": "Invoices", "url": "/landlord/invoices", "icons": [{ "src": "/icons/icon-192.png", "sizes": "192x192" }] },
        { "name": "បន្ទប់", "short_name": "Rooms", "url": "/landlord/units", "icons": [{ "src": "/icons/icon-192.png", "sizes": "192x192" }] }
    ],
    "screenshots": [
        { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "form_factor": "narrow", "label": "ងាយជួល — Dashboard" },
        { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "form_factor": "wide", "label": "ងាយជួល — Landlord Panel" }
    ]
}
```

---

## Phase 3 — Create `public/offline.html`

Standalone styled offline fallback page. Requirements:
- No external CSS/JS dependencies (everything must be inline — no CDN, no Google Fonts)
- Bilingual: Khmer primary, English secondary
- Brand colors: emerald #059669
- "Try Again" button that calls `window.location.reload()`
- "← Go to Dashboard" link to `/landlord/simple`

---

## Phase 4 — Rewrite `public/sw.js` (v2)

Replace entire file. Cache names: `ngeay-juol-shell-v2` and `ngeay-juol-fonts-v2`.

### Pre-cache on install
`/offline.html`, `/favicon.ico`, `/icons/icon-192.png`, `/icons/icon-512.png`, `/manifest.json`, `/`, `/login`

### Caching strategy per route

| Route / Asset | Strategy |
|---|---|
| `/build/*` (Vite hashed assets) | Cache-first |
| `/icons/*`, `/favicon.ico`, `/manifest.json` | Cache-first |
| `fonts.googleapis.com`, `fonts.gstatic.com` (cross-origin) | Cache-first (font cache) |
| `/`, `/login`, `/landlord/simple` | Stale-while-revalidate |
| `/landlord/invoices*` | Network-only — NEVER cache |
| `/landlord/monthly-billing*` | Network-only — NEVER cache |
| `/landlord/payments*` | Network-only — NEVER cache |
| `/landlord/tenants*` | Network-only — NEVER cache |
| `/landlord/rentals*` | Network-only — NEVER cache |
| `/landlord/subscription*` | Network-only — NEVER cache |
| `/livewire*` | Network-only (pass-through) |
| `/api*` | Network-only (pass-through) |
| `/locale*`, `/logout` | Network-only |
| All other navigation (`mode === 'navigate'`) | Network-first → `/offline.html` fallback |
| Non-GET requests | Pass-through, do not intercept |

On activate: delete all caches not in `[SHELL_CACHE, FONT_CACHE]`.

---

## Phase 5 — Wire PWA Tags on All Pages

### 5.1 `resources/views/welcome.blade.php`

Add inside `<head>` after favicon links:
```html
<!-- PWA -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#059669">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ងាយជួល">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
```

Add before `</body>`:
```html
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
  }
</script>
```

### 5.2 `resources/views/portal/layout.blade.php`

Same PWA `<head>` block + same SW registration script before `</body>`.

### 5.3 `app/Providers/Filament/LandlordPanelProvider.php`

Update `HEAD_END` renderHook to include all PWA tags:
- `<link rel="manifest" href="/manifest.json">`
- `<meta name="theme-color" content="#059669">`
- `<meta name="mobile-web-app-capable" content="yes">`
- `<meta name="apple-mobile-web-app-capable" content="yes">`
- `<meta name="apple-mobile-web-app-status-bar-style" content="default">`
- `<meta name="apple-mobile-web-app-title" content="ងាយជួល">`
- `<link rel="apple-touch-icon" href="/icons/icon-192.png">`

Update `BODY_END` renderHook:
- SW registration must use `window.addEventListener('load', ...)` wrapper
- Also render: `@include('filament.components.pwa-install-banner')`

---

## Phase 6 — Install Banner

### Create `resources/views/filament/components/pwa-install-banner.blade.php`

Self-contained Blade component with inline styles and script. Requirements:

- Fixed position, bottom of screen, horizontally centered, `z-index: 9999`
- Max width 420px, width `calc(100% - 2rem)` on mobile
- Glassmorphism style: white background with blur, emerald border/shadow
- Shows app icon (`/icons/icon-192.png`), app name `ងាយជួල`, subtitle `Add to Home Screen · Works offline`
- **Install button:** label `ដំឡើង`, emerald gradient, triggers `deferredPrompt.prompt()`
- **Dismiss button:** label `Not now`, transparent with border
- Hidden (`display:none`) by default
- Show only when `beforeinstallprompt` fires, with 2500ms delay
- Do NOT show if `window.matchMedia('(display-mode: standalone)').matches`
- Do NOT show if `localStorage.getItem('pwa-banner-dismissed')`
- On dismiss: set `localStorage.setItem('pwa-banner-dismissed', '1')`, hide
- On install accepted: set same localStorage key, hide
- On `appinstalled` event: set same localStorage key, hide

### Include on all pages
- `welcome.blade.php`: `@include('filament.components.pwa-install-banner')` before SW script
- `portal/layout.blade.php`: `@include('filament.components.pwa-install-banner')` before `</body>`
- `LandlordPanelProvider` BODY_END: included via `Blade::render('@include(...)')`

---

## Acceptance Criteria

- [ ] Chrome DevTools → Application → Manifest: **no installability errors**
- [ ] Chrome Lighthouse PWA audit on `/` and `/landlord/simple`: no critical failures
- [ ] App installable from any page: `/`, `/login`, `/landlord/*`, `/portal/*`
- [ ] Offline: navigating to `/landlord/simple` shows styled `/offline.html`
- [ ] Financial routes never served from cache
- [ ] Install banner appears after 2.5s on first visit, dismissible, does not reappear
- [ ] Old `ngeay-juol-shell-v1` cache is deleted on SW activate

---

## Run After All Changes

```bash
npm run build
php artisan view:clear
php artisan cache:clear
php artisan config:clear
```

---

## Files to Create / Edit

| Action | File |
|---|---|
| ✨ Create | `public/icons/icon-192.png` |
| ✨ Create | `public/icons/icon-512.png` |
| ✨ Create | `public/icons/icon-maskable-512.png` |
| ✏️ Edit | `public/manifest.json` |
| ✨ Create | `public/offline.html` |
| ✏️ Edit | `public/sw.js` |
| ✏️ Edit | `resources/views/welcome.blade.php` |
| ✏️ Edit | `resources/views/portal/layout.blade.php` |
| ✏️ Edit | `app/Providers/Filament/LandlordPanelProvider.php` |
| ✨ Create | `resources/views/filament/components/pwa-install-banner.blade.php` |

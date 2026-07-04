# Localization

RentWise supports English and Khmer.

## Runtime Locale

Supported locales are configured in `config/app.php`:

```php
'supported_locales' => ['en', 'km'],
```

The locale switch route is `/locale/{locale}`. It stores the selected locale in session and in a one-year `locale` cookie.

`App\Http\Middleware\SetLocale` applies the locale in Filament panels and invoice document routes.

## Translation Files

Khmer translations live in:

```text
lang/km.json
```

Use Laravel's `__()` helper for UI strings that need translation.

## Fonts

Khmer font files are stored in:

```text
resources/fonts/
```

Dompdf font cache files may exist under:

```text
storage/fonts/
```

Browsershot/Chrome should be used for invoice PDFs because browser rendering shapes Khmer text correctly. Dompdf fallback may produce incorrect Khmer glyph shaping even when fonts are installed.

## Notes For New UI

- Keep panel labels, navigation labels, table columns, actions, and validation messages translatable.
- Do not hard-code Khmer strings in PHP classes unless they are data, not UI labels.
- Test invoice PDFs in Khmer after any invoice layout change.

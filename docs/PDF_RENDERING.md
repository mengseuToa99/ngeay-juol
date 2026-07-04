# PDF Rendering

Invoice PDFs are rendered by `App\Services\InvoicePdfService`.

## Preferred Renderer

Browsershot with headless Chrome is the preferred renderer. It renders the Blade invoice view with browser text shaping, which is required for reliable Khmer output.

Configuration lives in `config/services.php`:

- `services.browsershot.chrome_path`
- `services.browsershot.node_binary`
- `services.browsershot.npm_binary`
- `services.browsershot.node_module_path`
- `services.browsershot.include_path`
- `services.browsershot.chromium_arguments`

Environment variables:

```dotenv
BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
# BROWSERSHOT_NODE_BINARY=/usr/bin/node
# BROWSERSHOT_NPM_BINARY=/usr/bin/npm
# BROWSERSHOT_NODE_MODULE_PATH=/absolute/path/to/node_modules
```

## Fallback Renderer

If Browsershot throws an exception, the service logs a warning and falls back to Dompdf.

Dompdf is useful for basic fallback rendering, but it does not shape Khmer correctly. If Khmer invoice text appears broken, fix Chrome/Browsershot rather than tuning Dompdf.

## Paper Sizes

Supported invoice PDF sizes:

- `a4`
- `a5`
- thermal receipt layout, 80 mm wide

`App\Support\InvoicePaper` owns the paper-size mapping.

## Checklist

- Chrome or Chromium is installed.
- `BROWSERSHOT_CHROME_PATH` points to the executable.
- `npm install` or `npm ci` has installed Puppeteer.
- Node and npm are available to the PHP process.
- `storage/` is writable.
- Khmer fonts remain available under `resources/fonts/`.

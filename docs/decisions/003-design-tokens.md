# 003. Design tokens and theming

**Status:** accepted

- Colours are defined twice in `resources/css/app.css`: brand scales (`ink`, `gold`, `sand`) and semantic tokens (`--bg`, `--surface`, `--text`, `--primary`, `--accent`, …) that switch between light and dark.
- The theme (auto, light, dark) is stored in `localStorage` and applied by a tiny inline script in `<head>` before first paint, so there is no flash of the wrong theme.
- Text direction comes from the locale (`ar` → `rtl`). Components use logical utilities (`ms-`, `pe-`, `start-`, `end-`), and direction-sensitive icons flip with `rtl:-scale-x-100`. Numbers, prices and discount badges are pinned to `dir="ltr"` so they never reorder.
- Shoppers can choose Western (123) or Arabic-Indic (١٢٣) digits; `fmt_number()` and `fmt_price()` respect the choice.

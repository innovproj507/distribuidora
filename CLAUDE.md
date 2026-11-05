# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

POS / inventory / sales system for a single company (Distribuidora Arango), built on CodeIgniter 4.7 over MySQL database `distribuidora`. It replaced a custom-framework PHP app (same database, same schema) that was retired and deleted on 2026-11-03; a zip of that code and DB dumps are in `C:\laragon\respaldos\`. Spanish UI, code comments in Spanish.

Local URL: **http://distribuidora.test** (Laragon, Nginx vhost `C:\laragon\etc\nginx\sites-enabled\distribuidora.test.conf` — a manual, non-`auto.` file that listens on 80 and 8080 because Laragon's global port is 8080).

## Commands

All PHP must run with **PHP 8.2** (CI 4.7 requires it; the default `php` in the terminal is 8.1):

```bash
P=/c/laragon/bin/php/php-8.2.33-Win32-vs16-x64/php.exe
$P spark serve --port 8091                         # dev server
$P spark routes                                    # every route with its filters
$P /c/laragon/bin/composer/composer.phar install   # dependencies
```

No test suite yet. Verify by hitting routes and checking `writable/logs/`.

## Scope

Web (dashboard, ventas/POS, cotizaciones with PDF and public `/c` link, inventario, compras, traslados, conteos, lotes, alertas, clientes, reportes, configuración, usuarios, bitácora, apps), PWA `/m` and REST API `/api/v1` (63 endpoints). The `mantenimiento` module has a manifest but no routes/controllers (it was never installed in the legacy DB) — porting it means recovering its code from the backup zip.

Unknown routes go to `Errores::noEncontrado` (Routing `$override404`): JSON 404 under `/api/`, redirect to `/login` without session, otherwise a 404 page inside the layout.

## Architecture

- **Modules** — `app/Modules/{name}/manifest.php` (sidebar menu, `menu_order`, `depends`) + optional `routes.php` (receives `$routes`). `App\Libraries\ModuleManager` loads routes of modules installed in the `modules` table, in `id` order, and builds the sidebar (`core` `menu_top`/`menu_bottom` around the rest). All manifests are present so the menu is complete even for unported modules.
- **Filters** (`app/Config/Filters.php`): `auth` (redirect `/login`, 401 JSON for `/api/*`), `sucursal` (puts `empresa_actual`, `sucursal_actual`, `sucursales_disponibles`, `deposito_actual`, `depositos_disponibles` in session via `SucursalService::contexto()`, recalculated on every request), `movilauth` (redirect `/m/login`, 401 JSON for `/m/api/*`), `permiso:a,b` (passes if the user has any of the listed permissions). Group filters are **merged** with route filters (CI4 `RouteCollection::create`), so `permiso:` on a route inside an `auth,sucursal` group keeps both. Global `csrf` except `api/v1/*`; global `secureheaders`.
- **CSRF** — session-based, field `_csrf_token`, header `X-CSRF-TOKEN`, `regenerate = false` (POS does many AJAX posts). Every POST form needs `csrf_field()`; JS uses `csrf_hash()`. JSON callers should send `X-Requested-With: XMLHttpRequest` so a bad token returns 403 instead of a redirect.
- **`App\Libraries\Auth`** — same static API as legacy (`check()`, `can()`, `rol()`, `attempt()`...). Django pbkdf2 + bcrypt passwords; TOTP 2FA for `gerente`/`auditor`/`superadmin`. Permission matrix: `app/Config/Permisos.php` (`superadmin` gets everything).
- **`App\Libraries\Db`** — thin static wrapper over `db_connect()`: `row()`, `all()`, `value()`, `insert()` (returns id), `update()`, `begin/commit/rollback()`. Legacy SQL uses `?` placeholders, which are CI4 bindings too, so queries port verbatim. `numberNative = true` in `.env` makes MySQLi return native ints/floats like the legacy PDO did — needed because libraries use `strict_types`.
- **Business logic** lives in `app/Libraries`, shared by web, POS and PWA:
  - `VentaService::procesar()` — invoice number, ITBMS, payments (`ventas_pagos`), credit limit, kardex, FEFO lots. Caller wraps it in a transaction. Items without `deposito_id` use the session's active depot (fixes the legacy bug where web POS sales never reduced stock).
  - `InventarioService::registrar()` — the only way stock changes: updates `inventario.existencia` and writes `inventario_movimientos`. `descontarLotesFefo()` for `maneja_lotes` products.
  - `ProductoService` — replaces the legacy DDD layer (Register/Update/Delete/AddStock use cases + repositories): create with prices, per-depot stock and auxiliary units; update; delete (cleans inventario/precios/unidades, refuses if the product has kardex movements); `agregarStock()` with weighted average cost. Initial stock and manual stock now go through the kardex (legacy wrote `existencia` directly).
  - Movement types are the DB enum: `entrada`, `salida`, `ajuste`, `traslado_salida`, `traslado_entrada`. Legacy used `traslado_out`/`traslado_en`, which the enum rejects — transfers never saved in the old app. Physical-count adjustments write `ajuste` rows directly with explicit saldo (InventarioService treats `ajuste` as an outflow).
  - `NumeracionService`, `AuditService` (never throws), `TotpService`, `SmartInsightService`, `AlertaInventarioService` (regenerated on each visit to /inventario/alertas), `OrdenCompraAutoService`, `SimpleXlsxWriter::descarga()`, `CotizacionPdf` (Dompdf).
- **Multi-sucursal** (`SucursalService`, `/sucursales`, migration `2026-11-05-000001`): `branches` has `prefijo`, `mapa_url`, `latitud`, `longitud`. Access: superadmin/superuser/`gerente` (`ROLES_TODAS`) see every active branch; others see `user_profiles_sucursales` (assigned in `/usuarios/{id}/permisos`), or their profile's branch / the main one if none. Depot restriction (`user_depositos_permitidos`) applies per branch: no permitted depot in a branch = all its depots. The chosen branch is remembered in `user_profiles.sucursal_actual_id` (also the API default; API accepts `X-Sucursal-Id`). Switch: `POST /sucursales/cambiar`, `/sucursales/cambiar-deposito` (top bar + PWA "Más"). Facturas/cotizaciones use `NumeracionService::siguiente($tipo, $empresa, $sucursalId)` → `{prefijo sucursal}{sep}{prefijo}{sep}{n}` (e.g. `SID-FAC-000001`); no prefix = general series. New branch → its main depot; every new depot/product gets `inventario` rows with existencia 0 and minimo 0 (`poblarInventario`/`poblarProducto`). Reports take `?sucursal=` (empty = consolidated over the user's branches). Sales/quotes lists and detail are scoped to the active branch.
- **Single price in the UI** (business decision 2026-11-05): screens only show/edit price A (`tipo_precio = 'a'`, labelled "Precio"). B, C and promocional stay in `precios_productos` and in the backend/API (`tipo_precio` still accepted); they are preserved because product/price saves only touch fields present in the POST, purchases only update prices > 0 (the purchase form sends existing B/C, no margin defaults), and `margen_precio_b/c` are saved only if posted. Pre-change views: `C:\laragon\respaldos\vistas_antes_un_precio\`.
- **`App\Libraries\Fila`** — row usable as `$fila->campo` and `$fila['campo']`, like legacy models. Only passed to ported views that use object syntax (product edit/detail, provider/depot/category forms, purchase form, kardex product list); new code uses arrays.
- **Deleting records with history never hard-deletes**: clients with sales/quotes/balance, providers with purchases, users with sales/purchases/movements, depots with history, and products with kardex movements are deactivated or refused instead.
- `direcciones_cliente` columns are `tipo, direccion, ciudad, provincia, pais, estado` (no phone) — the legacy address form posted fields that don't exist.
- Configuration screens require `usuarios.gestionar` (no dedicated permission in the matrix); limpieza and apps require superuser. `ConfiguracionController::estadoServidor()` shows real disk/memory data (legacy showed random numbers).
- **Permissions are enforced per route** (`permiso:` filter) for every inventory screen. Legacy controllers rarely checked them, so a role missing e.g. `proveedores.ver` could open screens it now can't.
- **`BaseController`** — `render($vista, $data)` injects what the layouts expect (`$auth`, `$flash`, `$csrf_token`, `$empresa_actual`, `$sucursal_actual`, `$deposito_actual`...); `json()`, `jsonInput()`, `success()/error()/warning()` flash, `exigir($permiso, $destino)` returns a redirect or null; `empresaId()/sucursalId()/depositoId()`.
- **Flash messages** — helper `flash($tipo, $msg)` (types `success|danger|warning|info`, layouts read `$flash['danger']`), consumed by `render()`. Not CI4 flashdata.

## REST API (`/api/v1`)

- **Auth**: `POST /api/v1/login {username|email, password, codigo_2fa?}` → `data.token`; then `Authorization: Bearer <token>`. Token stored plain in `users.api_token` (format inherited from the legacy app; hashing it is a pending hardening item); a new login replaces it, `POST /api/v1/logout` revokes it, deactivated users are rejected. Roles in `ROLES_2FA` with TOTP enabled must send `codigo_2fa`. 10 failed logins per IP / 15 min → 429 (counter in cache).
- **No PHP session**: `ApiTokenFilter` loads the user into `Auth::usarUsuarioApi()`; `Auth::check/id/rol/can()` and `AuditService` read from there instead of `session()`. API controllers extend `Api\ApiController` (not `BaseController`, which would start a session). Context: user profile's empresa/sucursal, default depot = main depot (endpoints accept `deposito_id`, validated against the branch and the user's allowed depots).
- **Permissions**: every route has `permiso:` from the same matrix as the web (legacy API checked none). Exceptions handled in code: `PUT usuarios/{id}` (self or `usuarios.gestionar`).
- **Responses**: `{status: success|error, message, data|errors}`; 401 bad/missing token, 403 permission, 404 JSON for unknown `/api/*` paths (`Errores::noEncontrado`), 422 validation/business errors.
- **Prices are server-side**: `ventas/procesar` and `cotizaciones` take `{producto_id, cantidad, tipo_precio?=a|b|c}` and price from `precios_productos` (`VentaService::precioLista()`); any client `precio` is ignored. Discounts only with `descuentos.aplicar`. Sales validate stock in the depot.
- Business logic is shared with the web through `VentaService` (incl. `anular()`), `CotizacionService`, `CompraService`, `TrasladoService`, `ProductoService` — never duplicate it in API controllers.
- Deletes are soft (`estado = inactivo`) for productos, clientes, proveedores, depósitos (depósito refused if it has stock); categorías are hard-deleted (products set to NULL).
- Legacy API defects fixed: categorías used a nonexistent table, cotizaciones joined `customers`, compras/traslados used invalid columns/enums, several routes pointed to unimplemented methods, `usuarios/perfil` leaked the password hash and token, 2FA was bypassed.

## Views

Plain PHP, converted mechanically from the legacy engine:

| Legacy | CI4 |
|---|---|
| `View::layout('app')` | `$this->extend('layouts/app')` |
| `View::section('x')` / `View::endSection('x')` | `$this->section('x')` / `$this->endSection()` |
| `View::yield('x')` | `$this->renderSection('x')` |
| `View::e(...)` | `esc(...)` (null-safe) |
| `View::csrf()` | `csrf_field()` |
| `View::include('a.b', $d)` | `echo view('a/b', $d)` |
| `View::isActive(...)` | `is_active(...)` (app_helper) |
| `\App\Core\Auth` / `ModuleManager` | `\App\Libraries\...` |
| `Router::url('/c?...')` | `base_url('c?...')` |

When converting with a script: **never use `sed` for patterns with backslashes** (GNU sed treats `\L`, `\E` in replacements as case-conversion), and **never write a file when `preg_replace` returns null** — a failed regex once emptied every view. Data rows are arrays (`$row['campo']`), never objects.

## PWA (`/m`)

Same design as the sibling `arango` app. Static files in `public/movil/` (not `public/m/`, a real folder would shadow the route on Nginx). `sw.js` and the manifest are served by `MovilController` at `/m/sw.js` and `/m/manifest.webmanifest` so the service-worker scope is `/m/`. Bump `CACHE_NAME` in `public/movil/sw.js` and `?v=` in `app/Views/layouts/movil.php` when `app.css`/`app.js` change. Login posts to `/login` with `_origen=movil`.

## Deployment (Plesk/VPS)

PHP 8.2+, document root → `public/`, `writable/` writable by the web user, `.env` created on the server (not in git), `composer install --no-dev`, `CI_ENVIRONMENT = production`, set `app.baseURL`. User uploads live in `public/assets/uploads/` (gitignored). PWA install needs HTTPS.

## Open security findings (review of 2026-11-03)

Not fixed yet, in priority order: stored XSS in `Views/ventas/crear.php` (client fields inside Alpine attributes via `addslashes`/raw) and DOM XSS in `Views/ventas/pos.php` (`innerHTML` with `${p.nombre}`, `${c.cliente_nombre}`); web login rate limit kept in the session (bypassable by dropping the cookie); no attempt limit on `/auth/2fa/verificar`; `CI_ENVIRONMENT = development` exposes Kint/debug toolbar; unescaped insights in `Views/dashboard/index.php`; Nginx `autoindex on`; no forced HTTPS / `Secure` cookie / HSTS / CSP; logout via GET; plain non-expiring API tokens; no password policy in web user creation; unvalidated `deposito_id` in agregarStock / recepción de compra / conteo; orphan `user_depositos_permitidos` row for user_id 5; dev dependencies installed. No SQL injection, no known CVEs in dependencies.

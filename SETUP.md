# Setup

Laravel 13 + Inertia + Vue 3 + shadcn-vue + Spatie Permission. Corre sobre XAMPP (PHP 8.3).

## Stack

| Capa | Qué |
|---|---|
| Backend | Laravel 13.23, PHP 8.3 |
| SPA | Inertia + Vue 3 (`@inertiajs/vue3`) |
| UI | shadcn-vue (estilo `reka-nova`, JS no TS), Tailwind v4 |
| Permisos | spatie/laravel-permission v8 |
| Build | Vite 8, `@tailwindcss/vite` |

Páginas en `resources/js/Pages/*.vue`. Componentes UI en `resources/js/components/ui/`. Alias `@` → `resources/js`.

---

## 1. Extensiones de PHP (CRÍTICO)

XAMPP trae varias extensiones apagadas. Sin ellas la app truena con `could not find driver` o `Call to undefined function mb_strimwidth()`.

Editar `C:\xampp\php\php.ini` y **descomentar** (quitar el `;` del inicio):

```ini
extension=pdo_mysql
extension=mysqli
extension=mbstring
extension=exif
extension=curl
extension=intl
```

Guardar. **Después reiniciar Apache** — Apache lee php.ini una sola vez al arrancar; un "restart" graceful NO recarga extensiones. Usar **Stop → Start** en el XAMPP Control Panel (abrirlo como administrador si el botón está bloqueado).

Verificar:
```powershell
php -m | Select-String "pdo_mysql|mbstring|curl|intl"
```

---

## 2. Base de datos

Crear la base `proyecto` en MySQL (phpMyAdmin o CLI). Ajustar `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=proyecto
DB_USERNAME=root
DB_PASSWORD=
```

---

## 3. Instalar dependencias

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
pnpm install
pnpm run build
```

---

## 4. Correr

Opción A — servidor de Laravel (recomendado para dev, no depende de Apache):
```bash
php artisan serve
```
→ http://127.0.0.1:8000

Opción B — Apache de XAMPP:
→ http://localhost/laravel-app/public/

Dev con hot-reload (Vite):
```bash
pnpm run dev
```

---

## 5. shadcn-vue: agregar componentes

Para agregar un componente nuevo en el futuro:

```powershell
$shim = "$env:TEMP\shim"
New-Item -ItemType Directory -Force $shim | Out-Null
"@echo off`r`nexit /b 0" | Out-File -Encoding ascii "$shim\corepack.cmd"

$node25 = "$env:APPDATA\fnm\node-versions\v25.9.0\installation"
$env:PATH = "$shim;$node25;$env:PATH"
pnpm dlx shadcn-vue@2.8.0 add button card input -y
```

- El comando normal `shadcn-vue add` crashea (`ERR_VM_DYNAMIC_IMPORT_CALLBACK_MISSING`): Node 25 quitó `corepack`, que el CLI usa. El shim `corepack.cmd` no-op lo evita; los deps ya están instalados.
- Tras cada `add`: el CLI reescribe `resources/css/app.css` metiendo un `@import url(...Geist...)` de Google Fonts. **Borrarlo** — el proyecto usa Instrument Sans self-hosted.

---

## Notas

- **Pail** (`php artisan pail`) no corre en Windows: pide la extensión `pcntl`, inexistente en PHP para Windows. Ya se quitó del script `composer run dev`.
- **`<Toaster/>`** (vue-sonner) está instalado pero no montado. Montarlo una vez en un layout compartido para que `toast()` funcione.
- **Node 25** está por-usuario vía fnm (sin admin); el Node del sistema sigue siendo el 20. La app corre con cualquiera, solo el CLI de shadcn pide el 25.

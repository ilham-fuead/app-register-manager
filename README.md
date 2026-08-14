# App Register Manager

A Vue 3 SPA for managing app registrations with active/inactive status tracking, designed for universal project folder management.

**Author:** [ilham-fuead](https://github.com/ilham-fuead)  
**Organization:** Mandryn PHP Team  
**License:** MIT

---

## Features

- **App Registry**: Track multiple applications with metadata
- **Active/Inactive Status**: Toggle visibility with eye icon (fa-eye/fa-eye-slash) on cards and detail page
- **Dashboard Split**: Active apps shown first, inactive apps separated visually by a divider
- **Missing Folder Detection**: Apps whose folders were deleted show "Tidak Ditemui" label; re-activation blocked
- **SCM Integration**: Git repository info (URL, branch, last commit, dirty changed files - scrollable)
- **Stack Detection**: Auto-detect PHP, Node, Python frameworks
- **Notes System (Catatan)**: Add timestamped notes per app, paginated 5 per page
- **Service Registry**: Track third-party services per app
- **Auto-scan**: First launch scans `C:/laragon/www` automatically; manual "Segar Semula" thereafter

---

## Requirements

- PHP 7.4+ with PDO MySQL extension
- MySQL 8.x
- Node.js 18.x
- Apache with URL rewriting (mod_rewrite)

---

## Project Structure

```
C:\laragon\www\app-manager\           (source + API)
├── .htaccess                         ← SPA routing for /app-manager/
├── config.php                        ← Database configuration
├── src/                              ← Vue 3 source files
│   ├── App.vue                       ← Root component + footer
│   ├── views/Dashboard.vue           ← Card grid with active/inactive split
│   ├── views/Detail.vue              ← App detail with notes & services
│   ├── styles/main.css               ← Morandi palette stylesheet
│   └── api/index.js                  ← API client
├── api/                              ← PHP REST API
│   ├── apps.php                      ← Apps CRUD
│   ├── scan.php                      ← Folder scanner
│   ├── schema.sql                    ← Full DB schema
│   └── migrate_*.sql                 ← Incremental migrations
├── vite.config.js                    ← Builds to ../my-apps/dist

C:\laragon\www\my-apps\               (production output)
├── .htaccess                         ← SPA routing for /my-apps/
└── dist/                             ← Built Vue 3 SPA
    ├── index.html
    └── assets/
```

---

## Installation

### 1. Database

```bash
mysql -u root < api/schema.sql
```

For incremental migrations:
```bash
mysql -u root < api/migrate_active_apps.sql    # adds is_active column
mysql -u root < api/migrate_pin_apps.sql       # adds is_pinned column
mysql -u root < api/migrate_app_notes.sql      # adds app_notes table
mysql -u root < api/migrate_ulasan.sql         # adds ulasan column
```

### 2. Configuration

Edit `config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'app_manager');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Set root path on first launch via the ⚙ settings icon in the header.

### 3. Build (Development)

```bash
npm install
npm run dev    # Start Vite dev server at http://localhost:5173
```

### 4. Build (Production)

```bash
npm run build  # Outputs to C:\laragon\www\my-apps\dist\ (auto-cleans old assets)
```

---

## Production Deployment

### URL: `http://localhost/my-apps/`

Apache `.htaccess` in `C:\laragon\www\my-apps\` rewrites to `dist/index.html`:

```apache
DirectoryIndex dist/index.html index.html
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_URI} !^/app-manager/api/
  RewriteCond %{REQUEST_URI} !\.(css|js|map|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$
  RewriteCond %{REQUEST_URI} !^/my-apps/dist/
  RewriteRule ^ /my-apps/dist/index.html [L]
</IfModule>
```

API requests at `/app-manager/api/` are excluded from rewriting and served directly from `app-manager/api/`.

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/apps.php` | List all apps (supports filter: `?status=`, `?stack=`, `?search=`) |
| GET | `/api/apps.php?name={name}` | Get single app detail (with stacks, services, notes) |
| POST | `/api/apps.php` | Add new app manually (JSON: `name`, `path`, `notes`) |
| POST | `/api/apps.php?name={name}` | Add catatan note (JSON: `{ "note": "..." }`) |
| PUT | `/api/apps.php?name={name}` | Update app - body can include `active`, `pinned`, `services`, etc. |
| DELETE | `/api/apps.php?name={name}&note_id={id}` | Delete specific note |
| GET | `/api/scan.php?path={path}` | Trigger folder scan |

### Toggle Active Status

```bash
curl -X PUT "http://localhost/app-manager/api/apps.php?name=my-app" \
  -H 'Content-Type: application/json' \
  -d '{"active": true}'
```

Note: activating an app whose folder no longer exists returns HTTP 400.

### Pin/Unpin App

```bash
curl -X PUT "http://localhost/app-manager/api/apps.php?name=my-app" \
  -H 'Content-Type: application/json' \
  -d '{"pinned": true}'
```

### Update Services

```bash
curl -X PUT "http://localhost/app-manager/api/apps.php?name=my-app" \
  -H 'Content-Type: application/json' \
  -d '{"services": [{"service_name": "Firebase Auth", "service_type": "auth", "provider": "Google"}]}'
```

---

## UI Conventions (Bahasa Melayu)

| English | Bahasa Melayu |
|---------|---------------|
| Active | Aktif |
| Inactive | Tidak Aktif |
| Pinned | Disematkan |
| Clean | Bersih |
| Dirty | Kotor |
| Branch | Cawangan |
| Notes | Catatan |
| Last Commit | Komit Terakhir |
| Changed Files | Fail Diubah |
| Stack | Stack |
| Third-party Services | Perkhidmatan Pihak Ketiga |
| Folder not found | Tidak Ditemui |
| Refresh | Segar Semula |
| Rescan | Imbas Semula |

---

## Visual Design

- **Palette**: Morandi (cool cream + muted greens/warms)
- **Icons**: FontAwesome 6 (fa-eye, fa-eye-slash, fa-thumbtack, fa-code-branch, fa-layer-group, fa-plug, fa-note-sticky)
- **Fonts**: Inter (UI), JetBrains Mono (commit hashes, branches)
- **Layout**: 12-col grid, cards auto-fill min 340px

---

## License

MIT License - You can use, modify, and distribute this software freely, provided you retain this copyright notice.

```
© 2024-2026 ilham-fuead
Mandryn PHP Team
```
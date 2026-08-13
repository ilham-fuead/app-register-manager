# App Register Manager

A Vue 3 SPA for managing app registrations with active/inactive status tracking, built for Malaysian school admission systems (SPMB) environment.

**Author:** Mohd Ilhammuddin Bin Mohd Fuead  
**Organization:** Mandryn PHP Team  
**License:** MIT

---

## Features

- **App Registry**: Track multiple applications with metadata
- **Active/Inactive Status**: Toggle visibility with eye icon (fa-eye/fa-eye-slash)
- **Dashboard Split**: Active apps shown first, inactive apps separated visually
- **SCM Integration**: Git repository information (URL, branch, last commit)
- **Stack Detection**: Auto-detect PHP, Node, Python frameworks
- **Notes System**: Add timestamped catatan/ulasan to each app
- **Service Registry**: Track third-party services per app

---

## Requirements

- PHP 7.4+ with PDO MySQL extension
- MySQL 8.x
- Node.js 18.x
- Apache with URL rewriting (mod_rewrite)

---

## Installation

### 1. Server Setup

Place project files in your web root:

```
/var/www/html/app-manager/     (or C:\laragon\www\app-manager\ on Windows/Laragon)
├── .htaccess                   ← SPA routing configuration
├── config.php                  ← Database configuration
├── dist/                       ← Built Vue 3 SPA (production)
├── api/                        ← PHP REST API
└── src/                        ← Vue 3 source files
```

### 2. Database

Run migrations:

```bash
mysql -u root < api/schema.sql
mysql -u root < api/migrate_active_apps.sql
```

### 3. Configuration

Edit `config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'app_manager');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Build (Development)

```bash
npm install
npm run dev    # Start Vite dev server at http://localhost:5173
```

### 5. Build (Production)

```bash
npm run build  # Outputs to dist/
```

---

## Production Deployment

On Apache, ensure `.htaccess` is in the project root:

```apache
DirectoryIndex dist/index.html index.html
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !^/app-manager/api/
  RewriteRule ^ /app-manager/dist/index.html [L]
</IfModule>
```

Access at: `http://localhost/app-manager/`

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/apps.php` | List all apps with filtering |
| GET | `/api/apps.php?name={name}` | Get single app detail |
| POST | `/api/apps.php` | Add new app (JSON body: name, path) |
| PUT | `/api/apps.php?name={name}` | Update app (JSON body) |
| DELETE | `/api/apps.php?name={name}&note_id={id}` | Delete note |

### Toggle Active Status

```bash
curl -X PUT http://localhost/app-manager/api/apps.php?name=my-app \
  -H 'Content-Type: application/json' \
  -d '{"active": true}'
```

---

## Bahasa Melayu Features

- **Status**: Aktif (active) / Tidak Aktif (inactive)
- **Semutex**: Disematkan / Tidak disematkan
- **Kotor/Bersih**: Dirty / Clean SCM status
- **Cahakan**: Branch
- **Catatan/Ulasan**: Notes/Journal

---

## License

MIT License - You can use, modify, and distribute this software freely, provided you retain this copyright notice.

```
© 2024-2026 Mohd Ilhammuddin Bin Mohd Fuead
Mandryn PHP Team
```
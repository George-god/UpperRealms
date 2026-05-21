# Deploying Upper Realms

After `git clone`, several things exist only on your machine until you generate them on the server. **Do not commit secrets or Composer/npm caches to Git.**

## Not in the repository (by design)

| Path | Why ignored | What to do on the server |
|------|-------------|---------------------------|
| `.env` | Passwords, `APP_KEY` | `cp .env.example .env` then edit; `php artisan key:generate` |
| `vendor/` | Composer packages | `composer install --no-dev --optimize-autoloader` |
| `node_modules/` | npm packages | `npm ci` (only if building assets on server) |
| `storage/`, `bootstrap/cache/` (generated) | Runtime cache, sessions, logs | `chmod -R ug+rwx storage bootstrap/cache` |
| `public/build/` | Vite output (optional in Git) | `npm ci && npm run build` **or** commit build assets (see below) |

## Web server document root

Point Apache/Nginx to **`public/`**, not the repo root and not `legacy/`.

- Site URL `/` → Laravel (`public/index.php`)
- `/login` → Laravel auth (not `legacy/pages/login.php`)
- Legacy gameplay → `/game/classic/...` through Laravel routes

## Server setup (typical)

```bash
git clone <repo-url> /var/www/upperrealms
cd /var/www/upperrealms

composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env: APP_URL, DB_* (use mysql/mariadb for production)

php artisan key:generate
php artisan migrate --force

npm ci && npm run build   # frontend CSS/JS

php artisan config:cache
php artisan route:cache
chmod -R ug+rwx storage bootstrap/cache
```

### Database

Legacy code expects **MySQL/MariaDB**. In `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cultivation_rpg
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Import `legacy/database_full.sql` on a fresh database if migrations alone are not enough for your environment.

## Optional: commit compiled frontend (`public/build`)

If the server has no Node.js, build locally and commit:

```bash
npm ci
npm run build
git add public/build
git commit -m "Add Vite production build for deployment"
git push
```

The repo `.gitignore` may exclude `public/build` by default (Laravel standard). Remove that line if you choose to track build output.

## Troubleshooting

- **Legacy login page** → document root is wrong or you opened `/legacy/` directly. Use `public/` as root.
- **Unstyled pages / broken buttons** → run `npm run build` or deploy committed `public/build/`.
- **500 errors** → check `storage/logs/laravel.log`, file permissions, `.env`, and `APP_KEY`.

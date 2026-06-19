# Production Deployment Guide

This guide walks you through deploying the **MiniInfluencer** application to production.

---

## 1. Production Environment Variables

Ensure the following variables are configured securely in your production environment:

| Variable | Description | Production Value |
|---|---|---|
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Debug mode (enables stack traces) | `false` |
| `APP_URL` | Base application URL | `https://yourdomain.com` |
| `APP_KEY` | Application encryption key | Run `php artisan key:generate --show` |
| `DB_CONNECTION` | Database connection driver | `pgsql` |
| `DB_HOST` | Database host | e.g. `your-postgres-instance.com` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | e.g. `miniinfluencer_production` |
| `DB_USERNAME` | Database username | e.g. `db_user` |
| `DB_PASSWORD` | Database password | Secure random string |
| `REDIS_CLIENT` | Redis connection client | `predis` |
| `REDIS_HOST` | Redis host | e.g. `127.0.0.1` or Redis cloud URI |
| `REDIS_PASSWORD` | Redis password | Redis password (if any) |
| `REDIS_PORT` | Redis port | `6379` |
| `WEBHOOK_SECRET` | Secret key to verify HMAC webhook payloads | Secure random string |

---

## 2. Docker-Based Deployment (Recommended)

The codebase comes preconfigured with a multi-stage Docker build that runs **Nginx**, **PHP-FPM**, **Supervisor** (managing the queue worker), and a **Scheduler loop** in a single container.

### Deploying to Fly.io
1. Install the flyctl CLI:
   ```bash
   powershell -Command "iwr https://fly.io/install.ps1 -useb | iex"
   ```
2. Initialize the Fly application:
   ```bash
   fly launch
   ```
   *Select PostgreSQL and Redis database extensions when prompted.*
3. Deploy the application:
   ```bash
   fly deploy
   ```

### Deploying to Railway or Render
1. Create a new service pointing to your GitHub repository.
2. Railway and Render will automatically detect the `Dockerfile` at the root of the project.
3. Add a PostgreSQL database and a Redis database service in the dashboard.
4. Bind the environment variables listed in Section 1 to your Web service (connect DB/Redis variables using the platform's connection variables).
5. Deploy. The entrypoint script will automatically run migrations and launch the app.

---

## 3. Standard Ubuntu VPS Deployment (Laravel Forge or Manual)

If deploying to a raw virtual private server (e.g. AWS EC2, DigitalOcean, Linode), follow these steps:

### A. Server Prerequisites
Install PHP 8.3, Nginx, PostgreSQL, Redis, Composer, and Node.js:
```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-bcmath php8.3-zip php8.3-gd php8.3-intl php8.3-curl php8.3-xml nginx redis-server postgresql postgresql-contrib git unzip
```

### B. Directory & Composer Setup
1. Clone the repository to `/var/www/miniinfluencer`:
   ```bash
   git clone <repo-url> /var/www/miniinfluencer
   cd /var/www/miniinfluencer
   ```
2. Copy `.env.example` to `.env` and fill out production credentials:
   ```bash
   cp .env.example .env
   nano .env
   ```
3. Run PHP and frontend build steps:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install
   npm run build
   ```
4. Configure permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/miniinfluencer
   sudo chmod -R 775 /var/www/miniinfluencer/storage /var/www/miniinfluencer/bootstrap/cache
   ```

### C. Nginx Configuration
Create Nginx configuration `/etc/nginx/sites-available/miniinfluencer`:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/miniinfluencer/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Link and enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/miniinfluencer /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### D. Systemd Queue Worker
To ensure queue workers run persistently, create a systemd service file `/etc/systemd/system/laravel-worker.service`:
```ini
[Unit]
Description=Laravel queue worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/miniinfluencer/artisan queue:work --tries=3 --timeout=120

[Install]
WantedBy=multi-user.target
```
Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker.service
sudo systemctl start laravel-worker.service
```

### E. Cron Scheduler
Open the cron configuration:
```bash
crontab -e -u www-data
```
Add the following line to execute the Laravel scheduler every minute:
```cron
* * * * * cd /var/www/miniinfluencer && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Post-Deployment Verification
Visit your domain and verify:
1. **Health endpoint**: Check `/healthz` returns `{"status":"healthy", ...}` to verify database, Redis, and queue connections are fully active.
2. **System Health dashboard**: View `/system-health` to check the circuit breaker status, remaining token-bucket limits, and run a test webhook request to confirm the HMAC validation is functional.

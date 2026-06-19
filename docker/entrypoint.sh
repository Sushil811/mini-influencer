#!/bin/sh
set -e

# Wait for DB connection if DB_HOST is defined
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection ($DB_HOST)..."
    php -r '
    $host = getenv("DB_HOST");
    $port = getenv("DB_PORT") ?: 5432;
    $driver = getenv("DB_CONNECTION") ?: "pgsql";
    $db = getenv("DB_DATABASE");
    $user = getenv("DB_USERNAME");
    $pass = getenv("DB_PASSWORD");
    
    $dsn = "$driver:host=$host;port=$port;dbname=$db";
    $max_attempts = 30;
    $attempts = 0;
    
    while ($attempts < $max_attempts) {
        try {
            $pdo = new PDO($dsn, $user, $pass);
            echo "Database connected successfully!\n";
            exit(0);
        } catch (PDOException $e) {
            $attempts++;
            echo "Waiting for database... Attempt $attempts/$max_attempts\n";
            sleep(2);
        }
    }
    echo "Could not connect to database!\n";
    exit(1);
    '
fi

# Run optimization commands
echo "Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Execute Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

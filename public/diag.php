<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

echo 'php='.PHP_VERSION."\n";
echo 'sapi='.PHP_SAPI."\n";

foreach (['pdo_pgsql', 'pgsql', 'mbstring', 'intl', 'fileinfo', 'tokenizer', 'openssl', 'gd', 'zip', 'bcmath'] as $extension) {
    echo $extension.'='.(extension_loaded($extension) ? 'yes' : 'no')."\n";
}

echo 'autoload='.(is_file(__DIR__.'/../vendor/autoload.php') ? 'yes' : 'no')."\n";
echo 'build='.(is_file(__DIR__.'/build/manifest.json') ? 'yes' : 'no')."\n";
echo 'hot='.(is_file(__DIR__.'/hot') ? 'yes' : 'no')."\n";
echo 'app_config='.(is_file(__DIR__.'/../config/app.php') ? 'yes' : 'no')."\n";

$key = (string) (getenv('APP_KEY') ?: '');
echo 'app_key_set='.($key !== '' ? 'yes' : 'no')."\n";
echo 'app_key_placeholder='.(str_contains($key, 'GENERATE') ? 'yes' : 'no')."\n";
echo 'app_env='.(getenv('APP_ENV') ?: '')."\n";
echo 'db_connection='.(getenv('DB_CONNECTION') ?: '')."\n";

$url = (string) (getenv('DATABASE_URL') ?: '');
$host = parse_url($url, PHP_URL_HOST);
echo 'database_url_set='.($url !== '' ? 'yes' : 'no')."\n";
echo 'database_host='.(is_string($host) ? $host : '')."\n";
echo 'database_placeholder='.(str_contains($url, 'xxxx') || str_contains($url, 'YOUR_NEON') ? 'yes' : 'no')."\n";

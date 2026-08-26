web: vendor/bin/heroku-php-apache2 -i .user.ini public/
worker: php artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-time=3600

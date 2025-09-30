# Tilda → Bitrix24 интеграция с генерацией PDF

## Установка

1. Клонировать проект:

```
git clone <repo> /var/www/html
cd /var/www/html
```

2. Установить зависимости:

```
composer install --no-dev --optimize-autoloader
```

3. Настроить переменные окружения:

```
cp .env.example .env
```

##Отредактировать .env файл


4. Создать директории и права:

```
mkdir -p logs public/invoices public/invoices/tmp
chown -R www-data:www-data logs public/invoices
chmod -R 750 logs public/invoices
```

5. Настроить nginx и supervisor:
```
cp config/nginx.conf /etc/nginx/sites-available/yourdomain
ln -s /etc/nginx/sites-available/yourdomain /etc/nginx/sites-enabled/
cp config/supervisor.conf /etc/supervisor/conf.d/invoice_worker.conf
```

6. Перезапустить сервисы:
```
systemctl reload nginx
systemctl restart supervisor
```

## API endpoints

- `POST /webhook.php` - Приём данных от Tilda
- `GET /poll.php?t=TOKEN` - Проверка статуса генерации
- `GET /wait.php?t=TOKEN` - Страница ожидания

## Мониторинг

Логи находятся в директории `logs/`:
- `app.log` - основные логи приложения
- `php_errors.log` - ошибки PHP

Worker логи: `/var/log/invoice_worker.log`

Этот набор файлов создаёт полнофункциональное продакшен-готовое приложение с очередями, логированием, безопасностью и мониторингом.
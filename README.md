# TeklifPro

Lightweight MVC-style PHP application for managing quotations.

## Setup

1. Install dependencies:
   ```bash
   composer install
   composer dump-autoload
   ```
2. Copy environment file:
   ```bash
   cp .env.example .env
   ```
3. Configure database credentials in `.env`.
4. Set web server document root to `public/` or run the built-in server:
   ```bash
   php -S localhost:8000 -t public/
   ```

## Routes
- `/` dashboard (requires login)
- `/login` login form
- `/customers` customers list (placeholder)
- `/quotations` quotation list (placeholder)

## License
Apache License 2.0

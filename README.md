# TimeBox

Plan the day in boxes. PHP 8.1+ · MySQL 8 / MariaDB 10.5+ · vanilla JS/CSS.

## Requirements
PHP 8.1+ with `pdo_mysql` and `mbstring`, plus MySQL or MariaDB.

## Install
1. Import the database: `mysql -u root -p < schema.sql`
2. Put your MySQL credentials in `config.php`.
3. Run it (from this folder):
   `php -S localhost:8000 -t public public/index.php`
   then open http://localhost:8000
4. First visit → "Create the admin account". After that you log in.

On Apache/XAMPP, point the web root (or a virtual host) at `public/`.
On Nginx, send every request that isn't a real file to `public/index.php`.

## Structure
- `public/`        web root: `index.php` + `assets/` (css, js)
- `src/Controllers` read the request, call a service, send the response
- `src/Services`    the rules (auth, no overlaps, 5-min steps, 04:00–24:00)
- `src/Repositories` all SQL (behind interfaces)
- `src/Application.php` wires everything and runs one request
- `views/`         PHP templates

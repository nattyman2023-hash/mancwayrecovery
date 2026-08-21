# MancWay Recovery — Production Website

A dynamic, database-driven website for a vehicle recovery business, built with
plain **PHP 8** + **MySQL** and designed to run on **Hostinger shared hosting**.
No static demo, no WordPress — a real, admin-managed, production-ready site.

## What's included

| Area | Pages / Features |
|------|------------------|
| Public site | Home, Services list, Service detail, Areas, Booking form, Contact form, Testimonials, About, FAQ, 404, sitemap, robots |
| Admin panel | Dashboard, CRM enquiries (workflow + dispatch), Recovery Vehicles, Bookings (view + status), Messages inbox, Services CRUD, Areas CRUD, Testimonials (approve/edit), Settings, Change password |
| Database | `services`, `areas`, `bookings`, `recovery_vehicles`, `messages`, `testimonials`, `settings`, `admins` |

Everything on the public site (services, areas, reviews, contact details) is
**stored in MySQL and edited from the admin panel** — no code changes needed to
update content.

## Requirements

- PHP 8.0 or newer (8.2+ recommended)
- MySQL 5.7 / MariaDB 10.3+
- Apache with `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_expires` (all
  standard on Hostinger)
- HTTPS (free SSL via Hostinger → hPanel → SSL)

## Project structure

```
MancWay/
├── app/                       # private code (kept ABOVE public_html on Hostinger)
│   ├── bootstrap.php
│   ├── db.php                 # PDO connection
│   ├── helpers.php
│   ├── csrf.php
│   ├── auth.php
│   ├── config/
│   │   ├── config.php         # reads env / local config
│   │   └── config.local.example.php   # <-- copy to config.local.php
│   └── views/layout/          # header / footer / admin partials
├── database/
│   ├── schema.sql             # tables — import first
│   ├── seed.sql                # services, areas, settings, sample reviews
│   └── migration_crm.sql      # CRM workflow + recovery fleet (import after seed)
├── public/                    # <-- contents go into public_html/
│   ├── index.php, services.php, service.php, areas.php
│   ├── booking.php, contact.php, testimonials.php, about.php, faq.php
│   ├── 404.php, sitemap.xml.php, robots.txt, .htaccess
│   ├── setup.php              # one-time admin installer — DELETE after use
│   ├── admin/                 # admin panel
│   └── assets/                # css, js, img
├── .env.example
├── .gitignore
└── README.md (this file)
```

## Deploying to Hostinger (step by step)

### 1. Create the database
1. hPanel → **MySQL Databases**
2. Create a database, e.g. `u514321141_mancway`
3. Create a database user (e.g. `u514321141_mancway`) and a strong password
4. Add the user to the database with **ALL PRIVILEGES**

### 2. Import the schema + seed
1. hPanel → **phpMyAdmin** → select your database → **Import**
2. Import `database/schema.sql` first
3. Then import `database/seed.sql`
4. Then import `database/migration_crm.sql` to enable the CRM and recovery fleet

### 3. Upload the files
Upload with the **hPanel File Manager** or FTP (e.g. FileZilla):

- Upload the **contents of `public/`** into `/home/u514321141/public_html/`
  (the `index.php`, `admin/`, `assets/`, `.htaccess`, etc. go *inside*
  `public_html`).
- Upload the **`app/` folder** into `/home/u514321141/app/`
  (so it sits **above** `public_html` — not web-accessible). This keeps your
  config and code private.

The relative path `public_html/index.php` → `require __DIR__ . '/../app/bootstrap.php'`
resolves to `/home/u514321141/app/bootstrap.php`. ✔️ It just works.

> **Single-folder alternative (optional):** if you'd rather not place `app/`
> above `public_html`, upload the **entire project folder** into `public_html/`
> (so `public/` ends up at `public_html/public/` and `app/` at `public_html/app/`).
> Then in hPanel → **Domains** → manage your domain → set the **document root**
> to `public_html/public`. Now `public/index.php` is the entry point and its
> `require __DIR__ . '/../app/bootstrap.php'` resolves to `public_html/app/bootstrap.php`.
> Because the document root is `public_html/public`, the `app/`, `database/`
> and `.env` files are **outside** the web root and not directly accessible.
> The first method (app above public_html) is cleaner and recommended;
> this single-folder method works too once the document root is set.

### 4. Create your config file
Create `/home/u514321141/app/config/config.local.php` (copy from
`config.local.example.php` and fill in real values):

```php
<?php
return [
    'APP_ENV'        => 'production',
    'APP_URL'        => 'https://mancwayrecovery.co.uk',   // your real domain
    'DB_HOST'        => 'localhost',
    'DB_NAME'        => 'u514321141_mancway',
    'DB_USER'        => 'u514321141_mancway',
    'DB_PASS'        => 'YOUR_DB_PASSWORD',
    'DB_CHARSET'     => 'utf8mb4',
    'SESSION_SECRET' => 'a-long-random-string-here',
    'MAIL_TO'        => 'you@example.com',          // where bookings go
    'MAIL_FROM'      => 'no-reply@mancwayrecovery.co.uk',
];
```

### 5. Enable SSL & set the domain
1. hPanel → **SSL** → install free **Let's Encrypt SSL** for your domain
2. hPanel → **Domains** → make sure your domain points to `public_html`
3. The `.htaccess` forces HTTPS automatically

### 6. Run the one-time setup
1. Visit `https://mancwayrecovery.co.uk/setup.php` in your browser
2. Create your admin account (username + a strong password of 10+ characters)
3. **Delete `public_html/setup.php`** afterwards (for security)

### 7. Log in & personalise
1. Visit `https://mancwayrecovery.co.uk/admin/login.php`
2. Sign in with the account you just created
3. Go to **Settings** and update: phone, email, hours, social links, address
4. Edit **Services** and **Areas** to match your real offering & pricing
5. Approve/edit **Testimonials** (sample reviews are included — replace them)

---

## Security features

- **PDO prepared statements** everywhere — SQL-injection safe
- **CSRF tokens** on every form
- **Output escaping** (`e()`) — XSS safe
- **Admin passwords** hashed with bcrypt (`password_hash`)
- **`.htaccess`**: forces HTTPS, security headers, blocks dotfiles/private dirs,
  disables directory listing, denies `.sql`/`.env`/`.md`
- **`app/` kept above the web root** — config & code not web-accessible
- **One-time setup installer** — no default admin password in the database
- **Honeypot field** + server-side validation on public forms
- **Admin pages** are `noindex` and require login

## Going live checklist

- [ ] `app/config/config.local.php`: set the **3 marked values** — DB password, real domain (`APP_URL`), notification email (`MAIL_TO`). The `SESSION_SECRET` is already generated for you.
- [ ] `schema.sql` + `seed.sql` imported (phpMyAdmin)
- [ ] `migration_crm.sql` imported after the base schema and seed
- [ ] `app/` uploaded to `/home/u514321141/app/`; contents of `public/` into `public_html/` (recommended layout)
- [ ] SSL enabled (HTTPS works, redirects to https)
- [ ] `setup.php` run (created your admin) and **deleted**
- [ ] Real phone/email/hours set in **Admin → Settings**
- [ ] Real services & pricing entered in **Admin → Services**
- [ ] Sample reviews replaced in **Admin → Testimonials**
- [ ] `public/robots.txt` `Sitemap:` line set to your real domain
- [ ] Test a booking end-to-end (you should get an email)
- [ ] Google Search Console: submit `https://yourdomain/sitemap.xml.php`

## Customising the look

- **Colours:** edit the CSS variables at the top of `public/assets/css/style.css`
  (`--navy`, `--amber`, etc.)
- **Logo:** the supplied brand logo is `public/assets/img/logo.jpeg`; the public/admin headers, social preview image, and favicon reference it.
- **Services/areas/reviews/settings:** all editable from the admin panel — no
  code edits required.

## Local testing (optional)

You can run it locally with PHP's built-in server if PHP is installed:

```
cd MancWay/public
php -S localhost:8000
```
Then visit http://localhost:8000 — but you'll need a local MySQL and a
`config.local.php` pointing at it. Easiest is to test on Hostinger directly.

---

Built and maintained for MancWay Recovery.

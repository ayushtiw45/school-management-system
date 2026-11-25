# School Management System (Nursery to Class 8)

A web-based admin panel for a school's admissions, student records, fees and payments.
Built with **Laravel 13 (PHP 8.3+) + MySQL**, using Bootstrap 5. Works on mobile, tablet and desktop.

## What is built (Phase 1)

| Module | Features |
|---|---|
| **Admin Dashboard** | Total students, admissions (+ pending), total fees collected this session, today's collection, pending fees, class-wise boys/girls, recent admissions, recent payments |
| **Admission Management** | Manual admission form, public **online admission form** (`/apply`), auto admission number, class & section assignment, personal / parent / guardian / previous school details, photo and document upload, admission confirmation, printable admission form & receipt |
| **Student Management** | List with search and filters (class, section, status, gender), student profile, edit, documents, ID card (single or whole class), active / inactive, bulk promotion to the next class, transfer / leaving records |
| **Class & Section Management** | Classes (Nursery → Class 8 seeded), sections A/B/C…, academic sessions with a "current" session, class-wise listing |
| **Fees Management** | Custom fee categories (one-time, monthly, quarterly, half-yearly, yearly), class-wise fee structure grid, automatic assignment of dues to students (skips months before a mid-year admission), student concession %, extra/custom fees per student, edit discount / fine / due date, pending fee list |
| **Payment Management** | Cash, UPI, bank transfer, cheque, online (reference no.), partial payments, multiple fees in one receipt, late fine and discount at collection, unique receipt numbers, printable receipt (Print / Save as PDF), payment history with date & mode filters, admin can cancel a receipt (dues are restored) |
| **User & Role Management** | Secure login/logout, multiple staff accounts, roles: **Admin**, **Accountant**, **Admission Staff** |

Phase 2 (next): Fee Reports & Excel/PDF export, Expense Management, SMS/WhatsApp notifications.

### Roles

- **Admin**: everything, including classes, sessions, users, settings and cancelling receipts.
- **Accountant**: fee categories, fee structure, fee collection, receipts, pending fees. Can view students.
- **Admission Staff**: admissions, student records, documents, ID cards, promotion. No fee screens.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # SQLite for quick local testing
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000 and log in with **admin@school.test / password**.
**Change this password right after the first login** (top-right menu → Change Password).

Optional demo data (44 students, payments and 2 online applications):

```bash
php artisan db:seed --class=DemoDataSeeder
```

## Hosting with MySQL (shared hosting / VPS)

1. Create a MySQL database and user in cPanel / your server.
2. Upload the project and run `composer install --no-dev --optimize-autoloader`.
3. In `.env` set:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-school-domain.com
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=school_db
   DB_USERNAME=school_user
   DB_PASSWORD=your-password
   ADMIN_EMAIL=principal@yourschool.com
   ADMIN_PASSWORD=a-strong-password
   ```
4. Run `php artisan key:generate`, `php artisan migrate --seed --force`, then `php artisan config:cache`.
5. Point the domain's document root to the **`public/`** folder.
6. Make `storage/` and `bootstrap/cache/` writable by the web server.

Student photos and documents are stored privately in `storage/app/private` and are only shown to logged-in staff.
**Back up the database and the `storage/` folder daily.**

## First-time setup in the app

1. **Settings**: school name, address, phone, admission/receipt prefix, fee due day, late fine.
2. **Academic Sessions**: check the current session (e.g. 2026-27, April to March).
3. **Classes & Sections**: Nursery to Class 8 with sections A and B are created. Add or rename as needed.
4. **Fee Categories** and **Fee Structure**: set the amount per instalment for each class (the seeded amounts are only samples).
5. **Users & Roles**: create accounts for the accountant and admission staff.

## How fees work

- Each fee category has a frequency. When an admission is confirmed, the student's dues for the session are created
  from the class fee structure: monthly = 12 dues, quarterly = 4, half-yearly = 2, yearly = 1, one-time = 1 (admission only).
  Instalments that end before the admission month are skipped.
- A student's **concession %** is applied to categories marked "concession applicable".
- If the fee structure changes later, use **Fee Structure → Assign to all students** to add the new dues. Existing dues are never duplicated.
- On promotion, the new session's dues can be assigned automatically (without the admission fee). Old pending dues stay on the student's account.

## Tests

```bash
php artisan test
```

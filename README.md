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
| **Fee Reports & Analytics** | Daily, monthly, date-wise, class-wise, student-wise, pending, paid & unpaid, discount reports and the session collection summary. Every report downloads as **Excel (.xlsx)** or **PDF** |
| **Expense Management** | Staff salary (staff name + salary month), electricity & utilities, maintenance and custom categories, bill/voucher upload, expense register, monthly expense report with income vs expense |
| **Notifications & Reminders** | Fee-due reminders (manual from Pending Fees, or automatic daily), payment confirmation, admission confirmation, announcements to all / a class / a section, editable message templates, message log with retry, free WhatsApp click-to-chat button |

### Roles

- **Admin**: everything, including classes, sessions, users, settings, announcements, message settings and cancelling receipts.
- **Accountant**: fee categories, fee structure, fee collection, receipts, pending fees, reports, expenses, fee reminders. Can view students.
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

Optional demo data (44 students, payments, expenses, 2 online applications and logins accounts@school.test / office@school.test, password `password`):

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

7. Add **one cron job** (cPanel → Cron Jobs, every minute). It sends queued SMS/WhatsApp messages and the daily automatic reminders:
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

Student photos, documents and expense bills are stored privately in `storage/app/private` and are only shown to logged-in staff.
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

## Desktop app for schools (TDS School Management Software)

Schools download **TDS-School-Setup.exe** from your website, install it on a Windows laptop and use it offline.
Each installation keeps its data on that laptop in `%APPDATA%\TDS School Data` (database, photos, documents).
Installing a new version over the old one keeps the data.

**How it works**
- The installer contains a portable PHP and this app (`desktop/`), installs per-user (no admin rights) and adds
  *TDS School Management Software* and *Stop TDS School Management* shortcuts.
- `desktop/launch.ps1` creates the data folder and a per-computer `APP_KEY`, runs migrations, starts PHP's built-in
  server on `127.0.0.1:8765` (next free port up to 8775) and opens the browser.
- First launch shows a setup screen (school name + admin login). There is no default password in the desktop build.
- **Licensing:** 30-day trial (from the first run, also stored in the Windows registry), then read-only until a
  license key is entered. Keys are Ed25519-signed on your website and checked offline. A key works only for the
  Installation ID it was made for. Yearly, multi-year, lifetime or custom-date keys.

**One-time setup**
1. Upload the `website/` folder to Hostinger (below) and open `https://your-domain/license-admin/`.
   Set the admin password. Copy the **public key** it shows and download the **backup** (keep it safe; it contains
   the secret signing key).
2. In GitHub → this repository → Settings → Secrets and variables → Actions → **Variables**, add:
   `TDS_LICENSE_PUBLIC_KEY` (required), and optionally `TDS_VENDOR_PHONE`, `TDS_VENDOR_WHATSAPP` (e.g. 919876543210),
   `TDS_VENDOR_EMAIL`, `TDS_VENDOR_WEBSITE`, `TDS_TRIAL_DAYS`.
3. Publish a version: create a tag, e.g. `git tag v1.0.0 && git push origin v1.0.0` (or GitHub → Releases → Draft a new
   release → new tag `v1.0.0`). The **Build Windows installer** workflow builds and tests the installer on Windows and
   attaches `TDS-School-Setup.exe` to the release. The website's download button always points to the latest release.

**Selling a license:** the school opens *License* in the app and sends you the Installation ID → you create a key on
`/license-admin/` → send it (WhatsApp button) → they paste it and click Activate.

Notes: the installer is not code-signed, so Windows SmartScreen shows "Windows protected your PC" (More info → Run
anyway) until you buy a code-signing certificate. PHP source is readable on the school's laptop, so the license stops
ordinary users but cannot stop a determined programmer.

## Website on Hostinger

`website/` contains the product page (`index.html`, `assets/`) and the license admin (`license-admin/index.php`).

1. Edit the `window.SITE` block at the top of `website/index.html`: phone, WhatsApp, email, price.
2. hPanel → Websites → your domain → **File Manager** → open `public_html` → upload the *contents* of `website/`
   (`index.html`, `assets/`, `license-admin/`). Remove Hostinger's default `index.php`/`default.php` if present.
3. hPanel → Advanced → **PHP Configuration**: PHP 8.1 or newer, and the `sodium` extension enabled.
4. Open `https://your-domain/license-admin/` and finish the setup (step 1 above). Its private files are created in
   `tds-license-data/` **next to** `public_html` (not inside it), so they cannot be downloaded.
5. Turn on SSL (hPanel → Security → SSL) so the admin password is sent over HTTPS.

## Notifications (SMS / WhatsApp)

Without a gateway, every message is saved in **Message Log** with status "Not sent (no gateway)", so nothing is lost.
The **WhatsApp button** on Pending Fees opens WhatsApp with the reminder already typed. It is free and needs no gateway.

To send messages automatically, buy an SMS or WhatsApp plan from any provider that has an HTTP API (MSG91, Fast2SMS, Textlocal,
Interakt, WATI, AiSensy …) and add its details to `.env`. `{to}` becomes the mobile number (e.g. 919876543210) and `{message}` the text.

```
# SMS via a GET link
SMS_DRIVER=http
SMS_HTTP_METHOD=get
SMS_HTTP_URL="https://provider.example/api/send?apikey=YOUR_KEY&sender=SCHOOL&to={to}&message={message}"

# WhatsApp via a JSON POST
WHATSAPP_DRIVER=http
WHATSAPP_HTTP_METHOD=post
WHATSAPP_HTTP_URL="https://provider.example/v1/messages"
WHATSAPP_HTTP_TOKEN=YOUR_TOKEN
WHATSAPP_HTTP_BODY='{"phone":"{to}","text":"{message}"}'
```

Then run `php artisan config:cache`. Choose SMS / WhatsApp / both and edit the message templates in **Message Settings**.
Indian SMS needs the templates registered on **DLT**, and WhatsApp business messages need **approved templates** from your
provider. Keep the wording in Message Settings the same as the approved template.

Automatic fee-due reminders are **off** by default. Switch them on in Message Settings. They run daily at 10 AM, only for fees
overdue by the chosen number of days, and at most once a week per student. Run them by hand with `php artisan fees:send-reminders --force`.

## Tests

```bash
php artisan test
```

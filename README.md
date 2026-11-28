# TDS School Management Software (online, multi-school)

Web software for Nursery to Class 8 schools, sold as a monthly plan from your own website.
Any school registers on the website, gets its **own private account** (data is separated per school),
pays **₹1 for a 7-day trial** and then **₹599/month by Razorpay Autopay**. Until the ₹1 is paid every feature is locked.

Styled like thedigitalstrokes.in (brand orange `#FF4B32`, dark `#17191C`, Bricolage Grotesque + Inter).
Built with Laravel 13 (PHP 8.3+) and MySQL. Runs on normal shared hosting (Hostinger) — no VPS needed.

## What a school gets

| Module | Features |
|---|---|
| Dashboard | Students, admissions, today's and session fee collection, pending fees, class-wise strength, income vs expense |
| Admissions | Office form and a public online form per school (`/apply/{school}`), auto admission no., photo & documents, print form |
| Students | Search & filters, profile, ID cards, active/inactive, promotion, TC / leaving |
| Classes & sessions | Nursery–Class 8 created on sign-up, sections, academic sessions |
| Fees | Fee categories, class-wise structure, automatic dues, concession, extra fees, pending list |
| Payments | Cash / UPI / bank / cheque, partial payments, fine & discount, printable receipts, cancel receipt |
| Reports | 11 reports (daily, monthly, class-wise, student-wise, pending, discount, summary, expenses…) in Excel & PDF |
| Expenses | Categories, salary records, bills, monthly income vs expense |
| Users & roles | Admin, Accountant, Admission Staff logins per school |
| SMS / WhatsApp | Fee reminders, payment & admission messages, announcements, free WhatsApp click-to-chat |

## How the business works

- **Website** (`/`): company page in English and Hindi, pricing, FAQ, privacy / terms / refund / contact pages.
- **Register** (`/register`): school name, contact, mobile, email, password → school account with default classes, fee
  categories and settings. The school lands on **Plan & Billing**; every other screen is locked (🔒).
- **₹1 trial**: Razorpay Subscription with a ₹1 add-on paid now and the first ₹599 charged after 7 days by
  UPI Autopay / card. One ₹1 trial per school. Webhooks extend access after every monthly payment (+2 grace days).
- **Plan ends / Autopay cancelled / payment fails**: the school becomes read-only (view, print, reports), data is kept.
- **Owner panel** (`/super`): all schools (locked / active / ended), CSV export, give access manually (UPI / cash
  payments), block a school, Autopay list and monthly revenue, settings (contact, ₹1 / 7 days / ₹599, Razorpay keys).

Data separation: every table has `school_id`; a global scope limits every query to the logged-in user's school and is
applied before route-model binding, so another school's records return "not found". Validation rules
(`exists`/`unique`) are scoped the same way. Tests cover this.

## Put it on Hostinger (shared hosting)

Live address: **https://schoolapp.thedigitalstrokes.in** (a subdomain of the company site). The company WordPress site
stays in the main `public_html`; this app goes only into the subdomain's folder (usually `public_html/schoolapp`).

1. Get `tds-school-hostinger.zip` (GitHub → Actions → "Build Hostinger zip" → latest run → Artifacts, or from a
   release, or run `scripts/build-hostinger-zip.sh`).
2. hPanel → **Databases → MySQL Databases**: create a database and user.
3. hPanel → **Advanced → PHP Configuration**: PHP 8.3+, extensions `sodium`, `pdo_mysql`, `mbstring`, `fileinfo`,
   `zip`, `gd` on.
4. hPanel → **Domains → Subdomains**: create `schoolapp`. hPanel → **File Manager** → the subdomain folder
   (`public_html/schoolapp`): delete only a default index file if present, upload the zip there and **Extract**.
   That folder must directly contain `.htaccess`, `app`, `public`, `vendor` … (never extract into the main
   `public_html`, which holds the company website)
   (the root `.htaccess` serves everything from `public/`; `.env`, `storage`, `vendor` can't be opened from the web).
5. Open your domain → the **installer** checks the server and asks for the database details and your owner login.
   It deletes itself when done.
6. Log in → **Owner panel → Settings**: phone, WhatsApp, email and Razorpay:
   - Razorpay → API Keys: Key ID + Key Secret (Test mode first, then Live mode)
   - Razorpay → Webhooks: URL `https://your-domain/razorpay/webhook`, events `subscription.authenticated`,
     `subscription.charged`, `subscription.pending`, `subscription.halted`, `subscription.cancelled`,
     `subscription.completed`; paste the webhook secret in Settings
   - Subscriptions (UPI Autopay / card recurring) must be enabled on the Razorpay account
7. hPanel → **Security → SSL**: on. Optional: hPanel → Advanced → **Cron Jobs**, every minute:
   `cd ~/domains/your-domain/public_html && php artisan schedule:run` (daily automatic fee reminders).

Never share the Razorpay Key Secret in chat or email; enter it only in Owner panel → Settings.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # owner@tds.test / password (owner), admin@school.test / password (demo school)
php artisan db:seed --class=DemoDataSeeder   # optional sample students, payments, expenses
php artisan serve
```

## Tests

```bash
php artisan test
```

Covers fees, payments, reports & exports, expenses, notifications, data separation between schools, the lock,
sign-up, Razorpay billing (₹1 trial, monthly charge, webhooks) and the owner panel. They also pass on MySQL/MariaDB.

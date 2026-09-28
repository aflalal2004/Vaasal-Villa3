# Vaasal Villa Integrated Management System (VVIMS)

A complete, self-contained **PHP + MySQL** system for **www.vaasalvilla.com** that combines:

| # | Module | What it does |
|---|--------|--------------|
| 1 | **Public website** | Home, rooms & live availability, online booking, manage/cancel booking, live restaurant menu, contact/enquiry form |
| 2 | **Villa / hotel management** | Front desk, room rack, availability calendar, check-in/out, **key-card control**, guest directory, rooms & room types |
| 3 | **Online booking management** | Website bookings, walk-in/phone bookings, **OTA & tour-operator channel API** (Booking.com, Expedia, tour operators) |
| 4 | **Restaurant POS** | Cashier terminal: **dine-in, takeaway, existing-guest / room-service orders**, KOT to kitchen, settle (cash/card/online/voucher), **charge to room**, receipts |
| 5 | **Kitchen display (KOT)** | Live ticket board by station (kitchen / bar / pastry) with new → preparing → ready → served |
| 6 | **Billing** | Guest folio (room charges, restaurant, spa, laundry, tours…), payments, refunds, **invoice generation** (room & restaurant), pro-forma folio |
| 7 | **Staff management** | Staff accounts, **attendance / clock-in-out / time tracking**, hours & labour cost, housekeeping tasks, staff self-service portal |
| 8 | **Admin** | Dashboard, reports (occupancy, ADR, RevPAR, channels, payments, top items, staff hours, CSV), **roles & permissions matrix**, settings, activity log |

All modules share **one MySQL database** (`vaasal_villa_ims3`) and **one PHP service layer** (`includes/services.php`), so a booking made on the website appears instantly at the front desk, a restaurant bill charged to a room lands on that guest's folio, and check-out produces a single invoice with everything on it.

---

## 1. Requirements

* XAMPP (Apache + MySQL/MariaDB) with **PHP 8.0+** (tested on PHP 8.2.12 / MariaDB 10.4.32)
* A modern browser (Chrome, Edge, Firefox, Safari)

No Composer or Node is required, and there are no code dependencies. The system runs fully offline. The only thing that needs internet is the **demo** website photos and videos (Unsplash/Pexels links). Once you upload your own media in *Admin → Website Media*, nothing depends on outside services.

## 2. Installation (XAMPP, Windows)

1. **Copy / extract** the project so this file is at
   `C:\xampp\htdocs\vaasal_villa_integrated_management_system3\README.md`
2. Open the **XAMPP Control Panel** and **Start** `Apache` and `MySQL`.
3. **Import the database** — either:
   * **phpMyAdmin:** open <http://localhost/phpmyadmin> → **Import** → choose `database.sql` → **Go**, or
   * **Command line:**
     ```bash
     C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\vaasal_villa_integrated_management_system3\database.sql
     ```
   The script creates the database `vaasal_villa_ims3` itself. It only touches its own database — other projects' databases are never dropped or modified. Re-importing resets this project to fresh demo data.
4. If your MySQL root user has a password or you use another port, edit `config/config.php`.
5. Open the system:

| Module | URL |
|--------|-----|
| Public website | <http://localhost/vaasal_villa_integrated_management_system3/> |
| Staff sign-in (all roles) | <http://localhost/vaasal_villa_integrated_management_system3/login.php> |
| Admin dashboard | <http://localhost/vaasal_villa_integrated_management_system3/admin/> |
| POS terminal (cashier) | <http://localhost/vaasal_villa_integrated_management_system3/pos/> |
| Kitchen display (KOT) | <http://localhost/vaasal_villa_integrated_management_system3/pos/kitchen.php> |
| Staff portal (clock in/out, tasks) | <http://localhost/vaasal_villa_integrated_management_system3/staff/> |
| Availability API (JSON) | <http://localhost/vaasal_villa_integrated_management_system3/api/availability.php?check_in=2026-10-01&check_out=2026-10-03> |
| Channel API (OTAs / tour operators) | `POST http://localhost/vaasal_villa_integrated_management_system3/api/channel.php` |

The base URL is auto-detected, so the folder can be renamed; set `APP_BASE_URL` in `config/config.php` only if detection fails.

### Upgrading an existing install (website photos & video)

If you imported `database.sql` **before** the media update, run the upgrade once (it is safe to run again and keeps your data):

```bash
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\vaasal_villa_integrated_management_system3\database_media_upgrade.sql
```

## 2a. Website photos, video & animation

The public website now has:

* **Hero background video**: muted, looping and autoplaying, with a poster image while it loads. It uses a lighter file on phones (`<source media>`) and has a pause/play button.
* **Promo video band** ("Where the jungle meets the ocean"): plays only while on screen, and ▶ plays it with sound.
* **Photos everywhere**: room cards, experience cards, the dining and about sections, and photo banners on every inner page.
* **Room detail pages** (`room.php?type=…`): a photo slideshow with thumbnails, key facts, amenities and a live availability check.
* **Gallery** (`gallery.php`): a masonry grid with category filters and a full-screen **lightbox** (keyboard ← → Esc and swipe on phones).
* **Animations**: fade/slide-in on scroll, animated counters, slow zoom on banner photos, hover zoom on images, a sticky header and a back-to-top button.
* **Accessibility**: visitors with *Reduced motion* turned on see no autoplay video and no animations, and content never depends on JavaScript to appear. If a video fails to load, the poster photo is shown.

**Manage it all in Admin → Website Media** (administrator):

| What | How |
|------|-----|
| Hero video (desktop), hero video for phones, poster image, promo video | Upload MP4/WebM (up to 40 MB) **or** paste an `https://` link. Tick *Remove* to turn a video off |
| Gallery photos | Upload JPG/PNG/WebP (up to 8 MB) or paste an `https://` link; set caption, category, sort order and *Show*. Link a photo to a room type to add it to that room's slideshow |
| Room type main photo | **Admin → Rooms & Types → Edit** → upload or paste a URL |

Uploads are saved to `assets/uploads/` with random names. The **real file type is checked** (a renamed `.php` is rejected), and scripts in that folder can never run (`.htaccess`).

**About the demo media:** the demo photos come from **Unsplash** and the videos from **Pexels**. Both are free to use under their licences and are loaded from their servers, so they need an internet connection. **Replace them with real photos and videos of Vaasal Villa before going live.** Upload your own in Website Media and the website stops depending on outside links. If a link ever stops working, the page falls back to the poster image or a coloured background.

## 3. Demo accounts

Every demo account uses the password **`Villa@123`** (click a username on the sign-in page to fill it in).
After sign-in each user lands on the right screen for their role.

| Username | Role | Lands on | Can do |
|----------|------|----------|--------|
| `admin` | Administrator | Dashboard | Everything |
| `manager` | General Manager | Dashboard | Everything except roles & system settings |
| `reception` | Front Office | Dashboard | Bookings, check-in/out, key cards, guests, folios & payments, housekeeping board, POS |
| `cashier` | Cashier | POS | POS, settle bills, charge to room, kitchen display |
| `waiter` | Waiter | POS | Take orders & send KOTs (cannot settle) |
| `kitchen` | Kitchen | Kitchen display | KOT board |
| `housekeep`, `housekeep2` | Housekeeping | Staff portal | Own cleaning tasks, clock in/out |
| `staff` | General staff | Staff portal | Clock in/out |

> **Change all demo passwords** (Admin → Staff) before using the system with real data.

## 4. Project structure

```
vaasal_villa_integrated_management_system3/
├── index.php              Website home (search widget, rooms, dining, experiences)
├── rooms.php              Rooms & live availability search
├── book.php               Online booking (instant confirmation, source = website)
├── manage-booking.php     Guest lookup / cancel (ref + email, 48h policy)
├── restaurant.php         Live menu (read from POS menu tables) + dining photos
├── room.php               Room detail: photo slideshow, facts, live availability
├── gallery.php            Photo gallery with filters + lightbox
├── contact.php            Enquiry form → Admin › Web Enquiries
├── login.php / logout.php Single staff sign-in for every module
├── database.sql           Schema + demo data (27 tables, 39 foreign keys)
├── database_media_upgrade.sql  Adds photos/gallery/video to an existing install
├── config/config.php      DB credentials, timezone, session settings   (web access denied)
├── includes/                                                           (web access denied)
│   ├── bootstrap.php      Config, secure session, headers, autoload
│   ├── db.php             PDO connection + query helpers + transactions
│   ├── auth.php           Login, rate-limiting, idle timeout, roles & permissions
│   ├── functions.php      Escaping, CSRF, flash messages, formatting, numbering
│   ├── services.php       ★ Shared business logic (availability, bookings, check-in/out,
│   │                        folio, invoices, refunds, POS totals, housekeeping, attendance)
│   ├── admin_header.php / admin_footer.php   Back-office layout (permission-driven menu)
│   └── site_header.php / site_footer.php     Website layout
├── admin/                 Back office
│   ├── index.php          Dashboard (occupancy, arrivals, departures, revenue, staff on duty)
│   ├── frontdesk.php      Arrivals / in-house / departures / room rack / night audit
│   ├── calendar.php       14-day availability grid (per room & per type)
│   ├── bookings.php, booking_form.php, booking_view.php   Booking list, create/edit, folio & actions
│   ├── guests.php         Guest directory & stay history
│   ├── keycards.php       Encode / validate / block key cards
│   ├── housekeeping.php   Task board, assignment, room status board
│   ├── rooms.php          Rooms & room types
│   ├── orders.php, menu.php               Restaurant orders; menu, categories & tables
│   ├── invoices.php, invoice.php, payments.php   Billing
│   ├── reports.php        Management reports + CSV export
│   ├── staff.php, attendance.php, roles.php      People & permissions
│   └── channels.php, messages.php, activity.php, settings.php
├── pos/
│   ├── index.php          POS terminal (single-page app)
│   ├── api.php            JSON API for POS + kitchen (CSRF-protected)
│   ├── kitchen.php        Kitchen display system
│   └── receipt.php        Printable 80 mm bill / receipt / KOT slip
├── staff/index.php        Staff self-service portal
├── api/
│   ├── availability.php   Public availability JSON
│   └── channel.php        OTA / tour-operator channel API (API-key auth)
├── admin/media.php        Website Media: hero/promo video, poster, gallery photos
└── assets/css (admin.css, pos.css, site.css, site-media.css), assets/js (app.js, pos.js, site.js), assets/uploads (secured)
```

## 5. How the modules work together (key flows)

### Booking flow
1. A booking arrives from **any channel** — website (`book.php`), front desk (`admin/booking_form.php`), or an **OTA / tour operator** through `api/channel.php`.
2. All channels call the same `create_booking()` which validates dates and occupancy and **locks the room-type rows** while checking availability, so two channels can't sell the last room twice.
3. The booking shows up immediately on the **dashboard, front desk, calendar** and **reports** (by channel, with commission).

### Check-in → stay → check-out
1. **Front Desk → Arrivals → Check in**: pick a free, available room of the booked type, choose the number of key cards → the room becomes *occupied* and **key cards are encoded** (valid until check-out time).
2. During the stay: **night audit** posts nightly room charges to the **folio**; staff post extras (spa, laundry, minibar, tours); the **POS charges restaurant bills to the room**; payments/deposits are recorded.
3. **Check-out** posts any remaining nights (early departures are charged only for nights stayed), refuses to close with an outstanding balance unless *city ledger* is ticked, blocks check-out while the guest has an **open restaurant order**, **generates the invoice**, **deactivates key cards**, sets the room to *available / dirty* and **auto-creates a housekeeping task**. Overpayments show as guest credit and can be refunded.

### POS flow
1. Cashier/waiter opens the **POS** → *Dine-in* (pick table), *Takeaway*, or *Guest / Room* (pick an in-house guest). Tapping a menu item with no ticket open starts a quick takeaway.
2. Add items (with notes, e.g. "no chilli") → **Send to kitchen** creates a **KOT** (more items later create more KOTs on the same order).
3. The **Kitchen display** shows the tickets by station, flags tickets older than 20 minutes, and beeps on new ones. Chefs mark them *preparing → ready → served*.
4. **Settle**: discount, cash (with change calculation) / card / online / voucher → payment + **restaurant invoice** + receipt, table released.
   **Charge to room**: posts the bill to the guest folio (shown on the room invoice at check-out) and prints a room-charge slip for signature.
5. Voiding items and cancelling orders need the `pos.settle` permission (waiters can't).

### Billing
* Restaurant: subtotal − discount + service charge % + tax % (rates set in Settings).
* Rooms: folio charges + room tax % on room nights.
* Invoices are numbered `INV-000001…`, can be printed or saved as PDF (browser print), and only unpaid invoices can be voided.

### Staff & housekeeping
* Staff clock in/out on the **staff portal**; managers see the duty board, fix missed punches and view hours/estimated pay in **Attendance**.
* Housekeepers see only **their own tasks** and mark them started/done; finishing a clean sets the room to *clean*; supervisors verify (→ *inspected*). Finishing a maintenance task puts an out-of-order room back in service.

## 6. Channel API (booking sites & tour operators)

Each OTA / tour operator has its own API key (**Admin → Booking Channels → 🔑 Key**). Demo keys:

| Channel | Demo key |
|---------|----------|
| Booking.com | `ota_bookingcom_demo_key_1234567890` |
| Expedia | `ota_expedia_demo_key_0987654321` |
| Island Tours Ltd (tour operator) | `to_islandtours_demo_key_5566778899` |

Examples (run from a terminal):

```bash
curl -X POST http://localhost/vaasal_villa_integrated_management_system3/api/channel.php -H "X-Api-Key: ota_bookingcom_demo_key_1234567890" -d "{\"action\":\"availability\",\"check_in\":\"2026-11-01\",\"check_out\":\"2026-11-04\"}"
```

```bash
curl -X POST http://localhost/vaasal_villa_integrated_management_system3/api/channel.php -H "X-Api-Key: ota_bookingcom_demo_key_1234567890" -d "{\"action\":\"create\",\"external_ref\":\"BDC-99812\",\"room_type_id\":2,\"check_in\":\"2026-11-01\",\"check_out\":\"2026-11-04\",\"adults\":2,\"rate_per_night\":199,\"guest\":{\"first_name\":\"Anna\",\"last_name\":\"Berg\",\"email\":\"anna@example.com\"}}"
```

```bash
curl -X POST http://localhost/vaasal_villa_integrated_management_system3/api/channel.php -H "X-Api-Key: ota_bookingcom_demo_key_1234567890" -d "{\"action\":\"cancel\",\"external_ref\":\"BDC-99812\"}"
```

| Action | Required fields | Response |
|--------|-----------------|----------|
| `availability` | `check_in`, `check_out` | room types with `available` count and rate |
| `create` | `room_type_id`, `check_in`, `check_out`, `guest.first_name`, `guest.last_name` (optional: `external_ref`, `adults`, `children`, `rate_per_night`, `guest.email/phone/country`, `special_requests`) | `201 {booking_ref, status}`. Re-sending the same `external_ref` is idempotent (`duplicate: true`) |
| `status` / `cancel` | `booking_ref` **or** `external_ref` | booking status. A channel can only see/cancel its own bookings |

Errors return `{"ok":false,"error":"…"}` with HTTP 401 (bad key), 400/422 (invalid data / no availability).

## 7. Database

One database, 27 InnoDB tables, 39 foreign keys:

```
roles ─< role_permissions >─ permissions            settings, activity_log, login_attempts
roles ─< users ─< attendance
room_types ─< rooms
guests ─< bookings >─ booking_sources (website / OTA / tour operator / walk-in / phone)
bookings >─ room_types, rooms
bookings ─< key_cards >─ rooms
bookings ─< folio_charges >─ orders           (restaurant bills charged to room)
bookings ─< payments >─ orders, invoices
bookings ─< invoices ─< invoice_items          (room invoices)
orders   ─< invoices                            (restaurant invoices)
restaurant_tables ─< orders ─< kots ─< order_items >─ menu_items >─ menu_categories
rooms ─< housekeeping_tasks >─ users
contact_messages                                 (website enquiries)
room_types ─< gallery_images                     (website photos; hero/promo video URLs live in settings)
```

## 8. Security

* Passwords hashed with `password_hash()` (bcrypt), auto-rehash on login.
* Login rate-limit: 5 failed attempts per username/IP within 15 minutes; all attempts logged.
* Session: HttpOnly + SameSite=Lax cookie, strict mode, ID regenerated at login, 60-minute idle timeout.
* **CSRF tokens** on every form and on every POS/Kitchen API call (`X-CSRF-Token` header).
* Every query uses **PDO prepared statements**; all output is HTML-escaped.
* Role-based permissions checked on every page **and** every API action (server-side, not just hidden menu items).
* `config/` and `includes/` are blocked from the web by `.htaccess`; directory listing disabled.
* Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
* Website forms have a honeypot anti-spam field; booking lookup is attempt-limited.
* Money-changing operations run inside **database transactions** with row locks.
* The full audit trail is in **Admin → Activity Log**.

**Before going live:** set `APP_DEBUG` to `false`, use a MySQL user with a strong password (not `root`), serve over HTTPS, change all demo passwords and API keys, and schedule database backups.

## 9. Configuration

* `config/config.php` — database credentials, timezone (`Asia/Colombo`), session timeout, login limits, debug mode.
* **Admin → Settings** — villa name/contacts, check-in/out times, currency symbol, restaurant tax %, service charge %, room tax %, invoice footer.
* **Admin → Roles & Permissions** — tick boxes per role; add custom roles.
* **Admin → Menu & Tables** — menu items (mark *sold out* instantly), categories (kitchen/bar/pastry station), tables.
* **Admin → Rooms & Types** — rates, occupancy, amenities (shown on the website).

## 10. Troubleshooting

| Problem | Fix |
|---------|-----|
| "Database connection failed" | Start MySQL in XAMPP; import `database.sql`; check `config/config.php` |
| Page not found | Folder must be inside `C:\xampp\htdocs\`; check the URL spelling |
| "Security token expired" | The page was open too long or you signed in elsewhere — reload the page |
| "Too many failed attempts" | Wait 15 minutes, or run `DELETE FROM login_attempts;` in phpMyAdmin |
| Times look wrong | Change `APP_TIMEZONE` in `config/config.php` |
| Want fresh demo data | Re-import `database.sql` (demo bookings are relative to today's date) |

## 11. Verified (website media update)

Tested on XAMPP Apache / PHP 8.2.12 / MariaDB 10.4.32 — **248 automated checks, all passing**:

* **Browser tests (110)** in headless Microsoft Edge at desktop 1280×900 and phone 390×844, covering home, rooms, every room page, gallery, dining, booking, contact, manage-booking and the 404 page:
  * no JavaScript/console or PHP errors, every image loads, no sideways scrolling, and every animated block ends up visible
  * the hero video really plays (phone and desktop files), the promo band plays when scrolled into view, and counters animate
  * the lightbox opens, moves to the next photo and closes with Esc; gallery filters and the room slideshow work; the phone menu opens
  * with *reduced motion*, nothing autoplays and nothing is hidden
* **Media admin & security (42):**
  * upload, edit, hide and delete gallery photos; set and clear videos; room photos
  * a PHP file disguised as a `.jpg` is rejected, and scripts in `uploads/` return 403 and never run
  * `config/` and `includes/` are blocked; only `https` links are accepted
  * all 21 internal links and all 82 remote photo/video links return 200
* **The full system suite (96)** re-run for regressions: bookings, channel API, roles, POS/KOT, billing, check-in/out, housekeeping and attendance.

Bugs found and fixed by these tests:

* Editing a room type changed its web address, which broke room links.
* On desktop, the phone-only video source made the hero video fall back to a still image.
* The CSRF rejection returned HTTP 500 under Apache (it now returns 403).

## 12. Verified (original build)

The delivered build was tested end-to-end on XAMPP PHP 8.2.12 / MariaDB 10.4.32 with an automated HTTP test (96 checks, all passing), covering:
website booking and overbooking prevention, manage-booking, contact form, CSRF rejection, channel API (create / duplicate / isolation between channels / cancel / bad key),
role-based login redirects and 403 blocking, every back-office page rendering without PHP errors, check-in with key cards, folio charges,
POS dine-in with multiple KOTs, tax and service calculation, kitchen status flow, cash settlement with change, room-service charge to room,
check-out rules (balance, open orders), invoice generation, early-departure billing and refunds, housekeeping ownership rules, attendance and reports/CSV.

-- =====================================================================
--  Vaasal Villa Integrated Management System (VVIMS)
--  Single MySQL database shared by: Website, Booking Engine, Villa/Front
--  Office, Housekeeping, Staff, Restaurant POS / Kitchen, Billing, Admin.
--
--  Import:  phpMyAdmin -> Import -> database.sql   (creates the DB itself)
--  Demo password for every demo user:  Villa@123
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS vaasal_villa_ims3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vaasal_villa_ims3;

-- Re-importing resets this project's own tables only.
DROP TABLE IF EXISTS gallery_images, activity_log, login_attempts, contact_messages, payments, invoice_items, invoices, folio_charges, order_items, kots, orders, restaurant_tables, menu_items, menu_categories, attendance, housekeeping_tasks, key_cards, bookings, booking_sources, guests, rooms, room_types, users, role_permissions, permissions, roles, settings;

-- ---------------------------------------------------------------------
-- 1. Security: roles, permissions, users
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60)  NOT NULL,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug  VARCHAR(60)  NOT NULL UNIQUE,
  name  VARCHAR(120) NOT NULL,
  grp   VARCHAR(60)  NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id       INT UNSIGNED NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  email         VARCHAR(160) NULL,
  phone         VARCHAR(40)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  department    VARCHAR(60)  NULL,
  hourly_rate   DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(60) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  success    TINYINT(1)  NOT NULL DEFAULT 0,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la (username, ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  module     VARCHAR(40)  NOT NULL,
  action     VARCHAR(255) NOT NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_date (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
  skey   VARCHAR(60) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Villa: room types, rooms, guests
-- ---------------------------------------------------------------------
CREATE TABLE room_types (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  slug         VARCHAR(100) NOT NULL UNIQUE,
  short_desc   VARCHAR(255) NULL,
  description  TEXT NULL,
  base_rate    DECIMAL(10,2) NOT NULL,
  max_adults   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_children TINYINT UNSIGNED NOT NULL DEFAULT 1,
  size_sqm     INT UNSIGNED NULL,
  bed_type     VARCHAR(60) NULL,
  amenities    VARCHAR(500) NULL,
  image_url    VARCHAR(500) NULL,
  color        VARCHAR(20) NOT NULL DEFAULT '#0f766e',
  is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE rooms (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_type_id        INT UNSIGNED NOT NULL,
  room_number         VARCHAR(20) NOT NULL UNIQUE,
  floor               VARCHAR(20) NULL,
  status              ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  housekeeping_status ENUM('clean','dirty','inspected','out_of_order') NOT NULL DEFAULT 'clean',
  notes               VARCHAR(255) NULL,
  CONSTRAINT fk_room_type FOREIGN KEY (room_type_id) REFERENCES room_types(id)
) ENGINE=InnoDB;

CREATE TABLE guests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(80)  NOT NULL,
  last_name   VARCHAR(80)  NOT NULL,
  email       VARCHAR(160) NULL,
  phone       VARCHAR(40)  NULL,
  country     VARCHAR(80)  NULL,
  id_type     VARCHAR(40)  NULL,
  id_number   VARCHAR(60)  NULL,
  address     VARCHAR(255) NULL,
  vip         TINYINT(1)   NOT NULL DEFAULT 0,
  notes       TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_guest_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Booking engine: sources (website / OTAs / tour operators), bookings
-- ---------------------------------------------------------------------
CREATE TABLE booking_sources (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  type           ENUM('direct','website','ota','tour_operator','walk_in','phone') NOT NULL,
  contact_person VARCHAR(100) NULL,
  email          VARCHAR(160) NULL,
  phone          VARCHAR(40)  NULL,
  commission_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  api_key        VARCHAR(64)  NULL UNIQUE,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_ref      VARCHAR(20) NOT NULL UNIQUE,
  guest_id         INT UNSIGNED NOT NULL,
  source_id        INT UNSIGNED NOT NULL,
  external_ref     VARCHAR(60)  NULL,
  room_type_id     INT UNSIGNED NOT NULL,
  room_id          INT UNSIGNED NULL,
  check_in         DATE NOT NULL,
  check_out        DATE NOT NULL,
  adults           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  children         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rate_per_night   DECIMAL(10,2) NOT NULL,
  room_total       DECIMAL(10,2) NOT NULL,
  status           ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') NOT NULL DEFAULT 'pending',
  payment_status   ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  special_requests TEXT NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_in_at    DATETIME NULL,
  checked_out_at   DATETIME NULL,
  INDEX idx_bk_dates (check_in, check_out),
  INDEX idx_bk_status (status),
  CONSTRAINT fk_bk_guest  FOREIGN KEY (guest_id)     REFERENCES guests(id),
  CONSTRAINT fk_bk_source FOREIGN KEY (source_id)    REFERENCES booking_sources(id),
  CONSTRAINT fk_bk_rtype  FOREIGN KEY (room_type_id) REFERENCES room_types(id),
  CONSTRAINT fk_bk_room   FOREIGN KEY (room_id)      REFERENCES rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_bk_user   FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Front office: key cards
-- ---------------------------------------------------------------------
CREATE TABLE key_cards (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  card_number VARCHAR(40) NOT NULL,
  booking_id  INT UNSIGNED NOT NULL,
  room_id     INT UNSIGNED NOT NULL,
  valid_from  DATETIME NOT NULL,
  valid_until DATETIME NOT NULL,
  status      ENUM('active','returned','lost','deactivated') NOT NULL DEFAULT 'active',
  issued_by   INT UNSIGNED NULL,
  issued_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at   DATETIME NULL,
  INDEX idx_kc_card (card_number),
  CONSTRAINT fk_kc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_kc_room    FOREIGN KEY (room_id)    REFERENCES rooms(id),
  CONSTRAINT fk_kc_user    FOREIGN KEY (issued_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Housekeeping & staff attendance
-- ---------------------------------------------------------------------
CREATE TABLE housekeeping_tasks (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id      INT UNSIGNED NOT NULL,
  assigned_to  INT UNSIGNED NULL,
  task_type    ENUM('checkout_clean','stayover_clean','turndown','deep_clean','inspection','maintenance') NOT NULL,
  priority     ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status       ENUM('pending','in_progress','done','verified') NOT NULL DEFAULT 'pending',
  notes        VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at   DATETIME NULL,
  completed_at DATETIME NULL,
  INDEX idx_hk_status (status),
  CONSTRAINT fk_hk_room FOREIGN KEY (room_id)     REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_hk_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_hk_by   FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE attendance (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  clock_in  DATETIME NOT NULL,
  clock_out DATETIME NULL,
  hours     DECIMAL(5,2) NULL,
  notes     VARCHAR(255) NULL,
  INDEX idx_att (user_id, work_date),
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Restaurant POS: menu, tables, orders, KOT
-- ---------------------------------------------------------------------
CREATE TABLE menu_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  station    ENUM('kitchen','bar','pastry') NOT NULL DEFAULT 'kitchen',
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE menu_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  description  VARCHAR(255) NULL,
  price        DECIMAL(10,2) NOT NULL,
  is_veg       TINYINT(1) NOT NULL DEFAULT 0,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_mi_cat FOREIGN KEY (category_id) REFERENCES menu_categories(id)
) ENGINE=InnoDB;

CREATE TABLE restaurant_tables (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_no VARCHAR(20) NOT NULL UNIQUE,
  area     VARCHAR(40) NOT NULL DEFAULT 'Main',
  seats    TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status   ENUM('free','occupied','reserved') NOT NULL DEFAULT 'free'
) ENGINE=InnoDB;

CREATE TABLE orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no       VARCHAR(20) NOT NULL UNIQUE,
  order_type     ENUM('dine_in','takeaway','room_service') NOT NULL,
  table_id       INT UNSIGNED NULL,
  booking_id     INT UNSIGNED NULL,
  customer_name  VARCHAR(120) NULL,
  covers         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status         ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  payment_status ENUM('unpaid','paid','room_charge') NOT NULL DEFAULT 'unpaid',
  subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax            DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes          VARCHAR(255) NULL,
  cashier_id     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at      DATETIME NULL,
  INDEX idx_ord_status (status),
  CONSTRAINT fk_ord_table   FOREIGN KEY (table_id)   REFERENCES restaurant_tables(id) ON DELETE SET NULL,
  CONSTRAINT fk_ord_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_ord_user    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE kots (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kot_no     VARCHAR(20) NOT NULL UNIQUE,
  order_id   INT UNSIGNED NOT NULL,
  status     ENUM('new','preparing','ready','served') NOT NULL DEFAULT 'new',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_kot_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_kot_user  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  menu_item_id INT UNSIGNED NULL,
  kot_id       INT UNSIGNED NULL,
  item_name    VARCHAR(120) NOT NULL,
  qty          INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price   DECIMAL(10,2) NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  notes        VARCHAR(255) NULL,
  status       ENUM('pending','sent','cancelled') NOT NULL DEFAULT 'pending',
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id)     REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_item  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_kot   FOREIGN KEY (kot_id)       REFERENCES kots(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. Billing: guest folio (room charges), invoices, payments
-- ---------------------------------------------------------------------
CREATE TABLE folio_charges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  charge_type ENUM('room','restaurant','minibar','laundry','spa','transport','tour','other') NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  order_id    INT UNSIGNED NULL,
  posted_by   INT UNSIGNED NULL,
  posted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_fc_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_fc_user    FOREIGN KEY (posted_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no     VARCHAR(20) NOT NULL UNIQUE,
  invoice_type   ENUM('room','restaurant') NOT NULL,
  booking_id     INT UNSIGNED NULL,
  order_id       INT UNSIGNED NULL,
  guest_id       INT UNSIGNED NULL,
  bill_to        VARCHAR(160) NOT NULL,
  subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax            DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_paid    DECIMAL(10,2) NOT NULL DEFAULT 0,
  status         ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
  created_by     INT UNSIGNED NULL,
  issued_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_guest   FOREIGN KEY (guest_id)   REFERENCES guests(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_user    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  qty         DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price  DECIMAL(10,2) NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_ii_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NULL,
  order_id    INT UNSIGNED NULL,
  invoice_id  INT UNSIGNED NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('cash','card','bank_transfer','online','voucher') NOT NULL,
  reference   VARCHAR(100) NULL,
  received_by INT UNSIGNED NULL,
  paid_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pay_date (paid_at),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_order   FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id)  REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. Website
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(160) NOT NULL,
  phone      VARCHAR(40)  NULL,
  subject    VARCHAR(160) NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  SEED / DEMO DATA
-- =====================================================================

INSERT INTO settings (skey, svalue) VALUES
('villa_name',        'Vaasal Villa'),
('villa_tagline',     'Your gateway to a tranquil tropical escape'),
('villa_address',     'Beach Road, Vaasal Bay'),
('villa_phone',       '+94 77 000 0000'),
('villa_email',       'reservations@vaasalvilla.com'),
('website',           'www.vaasalvilla.com'),
('currency_symbol',   '$'),
('tax_rate',          '10'),
('service_charge',    '10'),
('room_tax_rate',     '12'),
('check_in_time',     '14:00'),
('check_out_time',    '11:00'),
('invoice_footer',    'Thank you for staying with Vaasal Villa. We hope to welcome you again!');

INSERT INTO roles (id, name, slug, description) VALUES
(1, 'Administrator',  'admin',        'Full access to every module'),
(2, 'General Manager','manager',      'Operations, reports and staff'),
(3, 'Front Office',   'receptionist', 'Bookings, check-in/out, key cards, guest billing'),
(4, 'Cashier',        'cashier',      'Restaurant POS and payments'),
(5, 'Waiter',         'waiter',       'Takes restaurant orders, sends KOTs'),
(6, 'Kitchen',        'kitchen',      'Kitchen display / KOT'),
(7, 'Housekeeping',   'housekeeping', 'Room cleaning tasks'),
(8, 'General Staff',  'staff',        'Attendance only');

INSERT INTO permissions (id, slug, name, grp) VALUES
(1,  'dashboard.view',      'View management dashboard',        'General'),
(2,  'bookings.manage',     'Create & manage bookings',         'Front Office'),
(3,  'frontdesk.manage',    'Check-in / Check-out',             'Front Office'),
(4,  'guests.manage',       'Manage guests',                    'Front Office'),
(5,  'keycards.manage',     'Issue & manage key cards',         'Front Office'),
(6,  'rooms.manage',        'Manage rooms & room types',        'Villa'),
(7,  'housekeeping.manage', 'Assign & verify housekeeping',     'Housekeeping'),
(8,  'housekeeping.work',   'Work on own housekeeping tasks',   'Housekeeping'),
(9,  'pos.use',             'Use restaurant POS',               'Restaurant'),
(10, 'pos.settle',          'Settle bills / take payments',     'Restaurant'),
(11, 'kitchen.view',        'Kitchen display (KOT)',            'Restaurant'),
(12, 'menu.manage',         'Manage menu & tables',             'Restaurant'),
(13, 'billing.manage',      'Folios, invoices & payments',      'Billing'),
(14, 'reports.view',        'View reports',                     'Reports'),
(15, 'staff.manage',        'Manage staff & attendance',        'Staff'),
(16, 'roles.manage',        'Manage roles & permissions',       'Admin'),
(17, 'settings.manage',     'System settings & channels',       'Admin'),
(18, 'attendance.self',     'Clock in / out',                   'Staff');

-- Administrator: everything
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
-- Manager: everything except roles & settings
INSERT INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions WHERE slug NOT IN ('roles.manage','settings.manage');
-- Front office
INSERT INTO role_permissions (role_id, permission_id) SELECT 3, id FROM permissions WHERE slug IN
 ('dashboard.view','bookings.manage','frontdesk.manage','guests.manage','keycards.manage','housekeeping.manage','billing.manage','pos.use','pos.settle','attendance.self');
-- Cashier
INSERT INTO role_permissions (role_id, permission_id) SELECT 4, id FROM permissions WHERE slug IN ('pos.use','pos.settle','kitchen.view','attendance.self');
-- Waiter
INSERT INTO role_permissions (role_id, permission_id) SELECT 5, id FROM permissions WHERE slug IN ('pos.use','attendance.self');
-- Kitchen
INSERT INTO role_permissions (role_id, permission_id) SELECT 6, id FROM permissions WHERE slug IN ('kitchen.view','attendance.self');
-- Housekeeping
INSERT INTO role_permissions (role_id, permission_id) SELECT 7, id FROM permissions WHERE slug IN ('housekeeping.work','attendance.self');
-- General staff
INSERT INTO role_permissions (role_id, permission_id) SELECT 8, id FROM permissions WHERE slug IN ('attendance.self');

-- Password for all demo users: Villa@123
INSERT INTO users (id, role_id, full_name, username, email, phone, password_hash, department, hourly_rate) VALUES
(1, 1, 'System Administrator', 'admin',     'admin@vaasalvilla.com',     '+94 77 100 0001', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Management',   0),
(2, 2, 'Nadia Perera',         'manager',   'manager@vaasalvilla.com',   '+94 77 100 0002', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Management',  12),
(3, 3, 'Ravi Fernando',        'reception', 'frontdesk@vaasalvilla.com', '+94 77 100 0003', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Front Office',  6),
(4, 4, 'Sara Silva',           'cashier',   'cashier@vaasalvilla.com',   '+94 77 100 0004', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Restaurant',    5),
(5, 5, 'Kamal Jayasuriya',     'waiter',    NULL,                        '+94 77 100 0005', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Restaurant',    4),
(6, 6, 'Chef Arjun Das',       'kitchen',   NULL,                        '+94 77 100 0006', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Kitchen',       7),
(7, 7, 'Mala Wickrama',        'housekeep', NULL,                        '+94 77 100 0007', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Housekeeping',  4),
(8, 7, 'Dilan Rajapaksha',     'housekeep2',NULL,                        '+94 77 100 0008', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Housekeeping',  4),
(9, 8, 'Nimal Gardener',       'staff',     NULL,                        '+94 77 100 0009', '$2y$10$PDOfgAwmaI6TO.fQxpQME.PcYBG5VsGwFPs1j2n7Fm7QOAq2xhAMu', 'Grounds',       3);

INSERT INTO room_types (id, name, slug, short_desc, description, base_rate, max_adults, max_children, size_sqm, bed_type, amenities, color) VALUES
(1, 'Garden Deluxe Room', 'garden-deluxe', 'Calm room opening onto the tropical garden.',
 'A serene room surrounded by frangipani and palms, with a private terrace, rain shower and handcrafted teak furniture.',
 120.00, 2, 1, 32, 'Queen', 'Air conditioning,Wi-Fi,Rain shower,Garden terrace,Mini bar,Tea & coffee', '#15803d'),
(2, 'Ocean View Suite', 'ocean-view-suite', 'Spacious suite with a balcony facing the sea.',
 'Wake up to the sound of the waves. Separate lounge, king bed, deep soaking tub and a wide balcony over the bay.',
 210.00, 2, 2, 55, 'King', 'Air conditioning,Wi-Fi,Sea view balcony,Bathtub,Mini bar,Smart TV,Lounge area', '#0369a1'),
(3, 'Family Villa', 'family-villa', 'Two-bedroom villa for families and friends.',
 'Two bedrooms, a living area and a small kitchenette with a shaded veranda — ideal for families.',
 280.00, 4, 3, 90, 'King + Twin', 'Air conditioning,Wi-Fi,Kitchenette,Veranda,Two bathrooms,Smart TV', '#b45309'),
(4, 'Private Pool Villa', 'private-pool-villa', 'Secluded villa with its own plunge pool.',
 'Our signature retreat: a walled villa with a private plunge pool, outdoor shower, day bed and butler service.',
 390.00, 2, 2, 120, 'King', 'Private pool,Butler service,Outdoor shower,Day bed,Wi-Fi,Mini bar,Espresso machine', '#7c3aed');

INSERT INTO rooms (id, room_type_id, room_number, floor, status, housekeeping_status) VALUES
(1, 1, 'G101', 'Ground', 'available', 'clean'),
(2, 1, 'G102', 'Ground', 'occupied',  'clean'),
(3, 1, 'G103', 'Ground', 'available', 'dirty'),
(4, 1, 'G104', 'Ground', 'available', 'clean'),
(5, 2, 'S201', 'First',  'occupied',  'clean'),
(6, 2, 'S202', 'First',  'available', 'inspected'),
(7, 2, 'S203', 'First',  'maintenance','out_of_order'),
(8, 3, 'F301', 'Garden', 'available', 'clean'),
(9, 3, 'F302', 'Garden', 'available', 'clean'),
(10,4, 'PV01', 'Beach',  'occupied',  'clean'),
(11,4, 'PV02', 'Beach',  'available', 'clean');

INSERT INTO guests (id, first_name, last_name, email, phone, country, id_type, id_number, vip) VALUES
(1, 'Emma',    'Johansson', 'emma.j@example.com',   '+46 70 111 2222', 'Sweden',        'Passport', 'SE1234567', 0),
(2, 'Liam',    'Oconnor',   'liam.o@example.com',   '+353 87 555 1234','Ireland',       'Passport', 'IE7654321', 1),
(3, 'Priya',   'Nair',      'priya.n@example.com',  '+91 98 7654 3210','India',         'Passport', 'IN9988776', 0),
(4, 'Hans',    'Mueller',   'hans.m@example.com',   '+49 151 2345678', 'Germany',       'Passport', 'DE5544332', 0),
(5, 'Sophie',  'Martin',    'sophie.m@example.com', '+33 6 12 34 56 78','France',       'Passport', 'FR1122334', 0),
(6, 'Kenji',   'Tanaka',    'kenji.t@example.com',  '+81 90 1234 5678','Japan',         'Passport', 'JP6677889', 0);

INSERT INTO booking_sources (id, name, type, contact_person, email, commission_pct, api_key) VALUES
(1, 'Direct / Front Desk',   'direct',        NULL, NULL, 0,  NULL),
(2, 'vaasalvilla.com',       'website',       NULL, NULL, 0,  NULL),
(3, 'Booking.com',           'ota',           'Partner Support', 'partners@booking.example', 15, 'ota_bookingcom_demo_key_1234567890'),
(4, 'Expedia',               'ota',           'Partner Central', 'partners@expedia.example', 18, 'ota_expedia_demo_key_0987654321'),
(5, 'Island Tours Ltd',      'tour_operator', 'Mr. Perera', 'res@islandtours.example', 10, 'to_islandtours_demo_key_5566778899'),
(6, 'Walk-in',               'walk_in',       NULL, NULL, 0, NULL),
(7, 'Telephone',             'phone',         NULL, NULL, 0, NULL);

-- Bookings are relative to today so the demo always looks "live"
INSERT INTO bookings (id, booking_ref, guest_id, source_id, external_ref, room_type_id, room_id, check_in, check_out, adults, children, rate_per_night, room_total, status, payment_status, special_requests, created_by, checked_in_at) VALUES
(1, 'VV-100001', 1, 2, NULL,        1, 2,  CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 2 DAY, 2, 0, 120.00, 480.00,  'checked_in', 'partial', 'Late check-out if possible', 3, NOW() - INTERVAL 2 DAY),
(2, 'VV-100002', 2, 3, 'BDC-88231', 2, 5,  CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 3 DAY, 2, 1, 210.00, 840.00,  'checked_in', 'unpaid',  'Anniversary - flowers in room', 3, NOW() - INTERVAL 1 DAY),
(3, 'VV-100003', 3, 5, 'IT-5521',   4, 10, CURDATE() - INTERVAL 3 DAY, CURDATE(),                  2, 0, 390.00, 1170.00, 'checked_in', 'unpaid',  NULL, 3, NOW() - INTERVAL 3 DAY),
(4, 'VV-100004', 4, 4, 'EXP-77120', 3, NULL, CURDATE(),                CURDATE() + INTERVAL 4 DAY, 2, 2, 280.00, 1120.00, 'confirmed',  'unpaid',  'Baby cot please', NULL, NULL),
(5, 'VV-100005', 5, 2, NULL,        2, NULL, CURDATE() + INTERVAL 3 DAY, CURDATE() + INTERVAL 6 DAY, 2, 0, 210.00, 630.00, 'confirmed',  'paid',    NULL, NULL, NULL),
(6, 'VV-100006', 6, 7, NULL,        1, NULL, CURDATE() + INTERVAL 7 DAY, CURDATE() + INTERVAL 9 DAY, 1, 0, 120.00, 240.00, 'pending',    'unpaid',  'Airport pickup', 3, NULL);

INSERT INTO key_cards (card_number, booking_id, room_id, valid_from, valid_until, status, issued_by) VALUES
('KC-G102-0001', 1, 2,  NOW() - INTERVAL 2 DAY, CONCAT(CURDATE() + INTERVAL 2 DAY, ' 11:00:00'), 'active', 3),
('KC-S201-0002', 2, 5,  NOW() - INTERVAL 1 DAY, CONCAT(CURDATE() + INTERVAL 3 DAY, ' 11:00:00'), 'active', 3),
('KC-S201-0003', 2, 5,  NOW() - INTERVAL 1 DAY, CONCAT(CURDATE() + INTERVAL 3 DAY, ' 11:00:00'), 'active', 3),
('KC-PV01-0004', 3, 10, NOW() - INTERVAL 3 DAY, CONCAT(CURDATE(), ' 11:00:00'),                  'active', 3);

INSERT INTO folio_charges (booking_id, charge_type, description, amount, posted_by, posted_at) VALUES
(1, 'room',  'Room charge G102 (night 1)', 120.00, 3, NOW() - INTERVAL 2 DAY),
(1, 'room',  'Room charge G102 (night 2)', 120.00, 3, NOW() - INTERVAL 1 DAY),
(1, 'laundry','Laundry service',            18.00, 3, NOW() - INTERVAL 1 DAY),
(2, 'room',  'Room charge S201 (night 1)', 210.00, 3, NOW() - INTERVAL 1 DAY),
(3, 'room',  'Room charge PV01 (night 1)', 390.00, 3, NOW() - INTERVAL 3 DAY),
(3, 'room',  'Room charge PV01 (night 2)', 390.00, 3, NOW() - INTERVAL 2 DAY),
(3, 'room',  'Room charge PV01 (night 3)', 390.00, 3, NOW() - INTERVAL 1 DAY),
(3, 'spa',   'Couples massage 60 min',      95.00, 3, NOW() - INTERVAL 1 DAY);

INSERT INTO payments (booking_id, amount, method, reference, received_by, paid_at) VALUES
(1, 200.00, 'card',   'Deposit - Visa ****4411', 3, NOW() - INTERVAL 2 DAY),
(5, 630.00, 'online', 'Website prepayment',      NULL, NOW() - INTERVAL 5 DAY);

INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, priority, status, notes, created_by) VALUES
(3, 7, 'checkout_clean', 'high',   'pending',     'Guest departed this morning', 3),
(2, 7, 'stayover_clean', 'normal', 'in_progress', NULL, 3),
(5, 8, 'turndown',       'normal', 'pending',     'Anniversary: rose petals on bed', 3),
(7, NULL,'maintenance',  'urgent', 'pending',     'AC unit leaking', 2),
(6, 8, 'inspection',     'low',    'done',        NULL, 2);

INSERT INTO attendance (user_id, work_date, clock_in, clock_out, hours) VALUES
(3, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 07:58:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 16:05:00'), 8.12),
(4, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 10:55:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 19:10:00'), 8.25),
(7, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 07:30:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 15:30:00'), 8.00),
(6, CURDATE() - INTERVAL 1 DAY, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 10:00:00'), CONCAT(CURDATE() - INTERVAL 1 DAY, ' 22:00:00'), 12.00),
(7, CURDATE(), CONCAT(CURDATE(), ' 07:35:00'), NULL, NULL);

INSERT INTO menu_categories (id, name, station, sort_order) VALUES
(1, 'Breakfast',      'kitchen', 1),
(2, 'Starters',       'kitchen', 2),
(3, 'Mains',          'kitchen', 3),
(4, 'Seafood Grill',  'kitchen', 4),
(5, 'Desserts',       'pastry',  5),
(6, 'Beverages',      'bar',     6),
(7, 'Cocktails',      'bar',     7);

INSERT INTO menu_items (category_id, name, description, price, is_veg) VALUES
(1, 'Villa Breakfast Platter', 'Eggs your way, toast, fruit, juice', 14.00, 0),
(1, 'Hoppers & Curry',         'Egg hopper, dhal, pol sambol',       10.00, 0),
(1, 'Tropical Fruit Bowl',     'Seasonal fruits with yoghurt',        8.00, 1),
(2, 'Crispy Calamari',         'With lime aioli',                     11.00, 0),
(2, 'Vegetable Samosas',       'Three pieces, mint chutney',           7.00, 1),
(2, 'Prawn Cocktail',          'Classic Marie Rose sauce',            12.00, 0),
(3, 'Rice & Curry (Chicken)',  'Five curries, papadum',               15.00, 0),
(3, 'Rice & Curry (Veg)',      'Five vegetable curries',              12.00, 1),
(3, 'Kottu Roti',              'Chicken or vegetable',                11.00, 0),
(3, 'Beef Burger & Fries',     '200g patty, cheddar',                 16.00, 0),
(3, 'Pasta Arrabbiata',        'Spicy tomato, basil',                 13.00, 1),
(4, 'Grilled Catch of the Day','Market price fish, garlic butter',   24.00, 0),
(4, 'Jumbo Prawns',            'Chilli garlic, half dozen',           28.00, 0),
(4, 'Lobster Thermidor',       'Whole lobster, salad',                45.00, 0),
(5, 'Watalappan',              'Coconut & jaggery custard',            7.00, 1),
(5, 'Chocolate Lava Cake',     'Vanilla ice cream',                    9.00, 1),
(6, 'King Coconut',            'Fresh, chilled',                       4.00, 1),
(6, 'Fresh Lime Soda',         'Sweet or salted',                      4.00, 1),
(6, 'Ceylon Tea Pot',          'Single-estate black tea',              5.00, 1),
(6, 'Cappuccino',              NULL,                                   5.00, 1),
(6, 'Mineral Water 1L',        NULL,                                   3.00, 1),
(7, 'Arrack Sour',             'Coconut arrack, lime, bitters',       10.00, 1),
(7, 'Mojito',                  'Rum, mint, lime',                     11.00, 1),
(7, 'Local Lager',             '625ml',                                7.00, 1);

INSERT INTO restaurant_tables (table_no, area, seats, status) VALUES
('T1','Main',2,'free'),('T2','Main',4,'occupied'),('T3','Main',4,'free'),('T4','Main',6,'free'),
('P1','Pool Deck',2,'free'),('P2','Pool Deck',4,'free'),('B1','Beach',4,'free'),('B2','Beach',8,'reserved');

-- One open dine-in order with a KOT, and one closed order charged to a room
INSERT INTO orders (id, order_no, order_type, table_id, booking_id, customer_name, covers, status, payment_status, subtotal, service_charge, tax, total, cashier_id, created_at, closed_at) VALUES
(1, 'ORD-000001', 'dine_in',      2, NULL, 'Table T2', 3, 'open',   'unpaid',      41.00, 4.10, 4.51, 49.61, 5, NOW() - INTERVAL 30 MINUTE, NULL),
(2, 'ORD-000002', 'room_service', NULL, 2, 'Liam Oconnor (S201)', 2, 'closed', 'room_charge', 36.00, 3.60, 3.96, 43.56, 4, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY);

INSERT INTO kots (id, kot_no, order_id, status, created_by, created_at) VALUES
(1, 'KOT-000001', 1, 'preparing', 5, NOW() - INTERVAL 28 MINUTE),
(2, 'KOT-000002', 2, 'served',    4, NOW() - INTERVAL 1 DAY);

INSERT INTO order_items (order_id, menu_item_id, kot_id, item_name, qty, unit_price, line_total, status, notes) VALUES
(1, 7,  1, 'Rice & Curry (Chicken)', 2, 15.00, 30.00, 'sent', 'Less spicy'),
(1, 17, 1, 'King Coconut',           2,  4.00,  8.00, 'sent', NULL),
(1, 21, 1, 'Mineral Water 1L',       1,  3.00,  3.00, 'sent', NULL),
(2, 13, 2, 'Jumbo Prawns',           1, 28.00, 28.00, 'sent', NULL),
(2, 18, 2, 'Fresh Lime Soda',        2,  4.00,  8.00, 'sent', NULL);

INSERT INTO folio_charges (booking_id, charge_type, description, amount, order_id, posted_by, posted_at) VALUES
(2, 'restaurant', 'Restaurant ORD-000002 (room service)', 43.56, 2, 4, NOW() - INTERVAL 1 DAY);

INSERT INTO contact_messages (name, email, phone, subject, message) VALUES
('Anna Berg', 'anna.b@example.com', NULL, 'Wedding enquiry', 'Hello, we are planning a small beach wedding for 30 guests next year. Could you send packages?');

INSERT INTO activity_log (user_id, module, action) VALUES
(1, 'system', 'Database initialised with demo data');

-- ---------------------------------------------------------------------
-- Website media: room photos, gallery, hero & promo video
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_images (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_url    VARCHAR(500) NOT NULL,
  caption      VARCHAR(160) NULL,
  category     ENUM('villa','rooms','dining','spa','experiences') NOT NULL DEFAULT 'villa',
  room_type_id INT UNSIGNED NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gal_rtype FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (skey, svalue) VALUES
('hero_video_url',  'https://videos.pexels.com/video-files/1093662/1093662-hd_1280_720_30fps.mp4'),
('hero_video_mobile_url', 'https://videos.pexels.com/video-files/1093662/1093662-sd_640_360_30fps.mp4'),
('hero_poster_url', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80'),
('promo_video_url', 'https://videos.pexels.com/video-files/3571264/3571264-sd_960_540_30fps.mp4');

UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'garden-deluxe'      AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1602002418082-a4443e081dd1?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'ocean-view-suite'   AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'family-villa'       AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1613977257363-707ba9348227?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'private-pool-villa' AND image_url IS NULL;

INSERT INTO gallery_images (image_url, caption, category, room_type_id, sort_order)
SELECT * FROM (
  SELECT 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80' u, 'Main pool & lounge pavilion' c, 'villa' cat, NULL rt, 1 s UNION ALL
  SELECT 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1600&q=80', 'Infinity pool at sunset', 'villa', NULL, 2 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1600&q=80', 'The villa from above', 'villa', NULL, 3 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1600&q=80', 'Evenings by the pool', 'villa', NULL, 4 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=1600&q=80', 'Our private beach', 'villa', NULL, 5 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1600&q=80', 'Garden Deluxe — bright and airy', 'rooms', 1, 10 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?auto=format&fit=crop&w=1600&q=80', 'Garden terrace', 'rooms', 1, 11 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=1600&q=80', 'Ocean View Suite bedroom', 'rooms', 2, 12 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1600&q=80', 'Suite balcony view', 'rooms', 2, 13 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1600&q=80', 'Family Villa exterior', 'rooms', 3, 14 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1600&q=80', 'Family Villa living area', 'rooms', 3, 15 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1600&q=80', 'Private plunge pool', 'rooms', 4, 16 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?auto=format&fit=crop&w=1600&q=80', 'Pool villa lagoon views', 'rooms', 4, 17 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=1600&q=80', 'Seaside terrace restaurant', 'dining', NULL, 20 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1600&q=80', 'Chef''s tasting plates', 'dining', NULL, 21 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80', 'The Vaasal Kitchen dining room', 'dining', NULL, 22 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=1600&q=80', 'Ayurvedic massage', 'spa', NULL, 30 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1600&q=80', 'Spa rituals', 'spa', NULL, 31 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1600&q=80', 'Temple day tours', 'experiences', NULL, 40 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?auto=format&fit=crop&w=1600&q=80', 'Boat trips & snorkelling', 'experiences', NULL, 41 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80', 'Sunset on the bay', 'experiences', NULL, 42
) seed
WHERE NOT EXISTS (SELECT 1 FROM gallery_images);

-- techearl-labs / sql-injection / sqli-basic
-- Runs once on the first start of the MySQL container via
-- /docker-entrypoint-initdb.d/. Resets only when the data volume is dropped
-- (`docker compose down -v`).
--
-- Every grant and column type below is INTENTIONAL for the companion article's
-- exploit walkthrough. Do not copy this configuration into a real database.

USE shop;

-- ---- Products ------------------------------------------------------------

CREATE TABLE products (
  id INT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  price DECIMAL(10, 2) NOT NULL
);

INSERT INTO products (id, name, description, price) VALUES
  (1, 'Wireless Headphones',   'Bluetooth 5.3 ANC over-ear headphones',                249.00),
  (2, 'Mechanical Keyboard',   '60% wireless RGB hot-swappable',                       129.00),
  (3, 'USB-C Hub',             '7-in-1 USB-C dongle, 4K HDMI passthrough',              49.00),
  (4, 'Standing Desk Mat',     'Ergonomic cushioned anti-fatigue mat',                  79.00),
  (5, 'External SSD',          '2 TB NVMe USB 3.2 portable drive',                     159.00);

-- ---- Users (bcrypt-hashed passwords) -------------------------------------
--
-- For documentation only, the cleartext passwords are admin123 / alice123 /
-- bob123. The hashes are real bcrypt at cost 12, regenerated for each lab
-- build so they vary between machines.

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(60) NOT NULL,
  email VARCHAR(100) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, email, is_admin) VALUES
  ('admin', '$2y$12$j6rGxbj7LnLf8UHljNgHpOO03hUVFsPJMUxbj8BYJoMaL7fWJcaaG', 'admin@example.test', 1),
  ('alice', '$2y$12$uu0JghvSOWGdxEVdPskHf.UoafrNqhXETtXnjYW0DjhbcQco9AuOq', 'alice@example.test', 0),
  ('bob',   '$2y$12$HiaXHUazCuT/GANo63wNdumo/gtQaT.LxV7R54GTAapaUEyyWeiRq', 'bob@example.test',   0);

-- ---- Sessions ------------------------------------------------------------

CREATE TABLE sessions (
  id VARCHAR(64) PRIMARY KEY,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- Audit log -----------------------------------------------------------

CREATE TABLE audit_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event VARCHAR(255) NOT NULL,
  ip VARCHAR(45),
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- Second-order injection target ---------------------------------------
-- /track?ref=... writes to this table via a parameterised statement (so the
-- entry point is safe), but the /admin/reports endpoint reads the stored ref
-- value back into ANOTHER concatenated query, which is where the second-order
-- injection fires.

CREATE TABLE tracking (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ref VARCHAR(500),
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- Page-view analytics (the User-Agent / X-Tenant-Id injection target) -
-- The /track?page=... endpoint writes here with the User-Agent and the
-- X-Tenant-Id header value concatenated directly into the INSERT, by design.

CREATE TABLE page_views (
  id INT PRIMARY KEY AUTO_INCREMENT,
  path VARCHAR(255),
  user_agent VARCHAR(500),
  tenant_id VARCHAR(100),
  ip VARCHAR(45),
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- Application database user ------------------------------------------
-- INSERT/SELECT/UPDATE/DELETE on the shop database, plus FILE for the file
-- read step of the companion article. NOT a DBA (so xp_cmdshell-equivalent
-- escalation paths fail, as the article expects).

CREATE USER IF NOT EXISTS 'webapp'@'%' IDENTIFIED BY 'webapp123';
GRANT SELECT, INSERT, UPDATE, DELETE ON shop.* TO 'webapp'@'%';
GRANT FILE ON *.* TO 'webapp'@'%';
FLUSH PRIVILEGES;

-- techearl-labs / cross-site-scripting / xss-basic
-- Runs once on the first start of the MySQL container via
-- /docker-entrypoint-initdb.d/. Resets only when the data volume is dropped
-- (`docker compose down -v`).
--
-- The data layer here is fine. Every query in the app uses parameterised
-- statements. The vulnerabilities live entirely in the HTML rendering layer,
-- where untrusted strings get echoed without escaping. Do not copy the
-- rendering shape into a real application.

USE xss_lab;

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
  is_admin TINYINT(1) NOT NULL DEFAULT 0
);

INSERT INTO users (username, password, email, is_admin) VALUES
  ('admin', '$2y$12$.X/rnix/Tlxd9QyJKs6h1u2Rd.PR4BCy0sS/i0bKorSfTGgj8OiNO', 'admin@example.test', 1),
  ('alice', '$2y$12$6tPy/NfGiuG.Tt4WpABb5.AzaBiSs463UdTqNkkngTUksA/YThUKW', 'alice@example.test', 0),
  ('bob',   '$2y$12$vlCWQNknFnKT2TXuPmx4EuwUuX5JjkR74zwy1NAR2pMWjLZMtjSQi', 'bob@example.test',   0);

-- ---- Sessions ------------------------------------------------------------
-- The session id is a random 64-char hex string stored here on login. The
-- cookie that carries it on every subsequent request is set WITHOUT the
-- HttpOnly flag (see public/shared/auth.php) so document.cookie can read it
-- from JavaScript. That is the intentional misconfiguration that turns any
-- of the three XSS sinks into a session-theft primitive.

CREATE TABLE sessions (
  id VARCHAR(64) PRIMARY KEY,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- Comments ------------------------------------------------------------
-- /guestbook.php lists every row, body rendered raw. One seed comment so the
-- page is not empty before any exploit is attempted.

CREATE TABLE comments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO comments (user_id, body) VALUES
  (2, 'First post! Nice to have a guestbook again.');

-- ---- Application database user ------------------------------------------
-- INSERT/SELECT on the xss_lab database. No FILE, no DDL. The data layer is
-- not the target.

CREATE USER IF NOT EXISTS 'webapp'@'%' IDENTIFIED BY 'webapp123';
GRANT SELECT, INSERT, UPDATE, DELETE ON xss_lab.* TO 'webapp'@'%';
FLUSH PRIVILEGES;

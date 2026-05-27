// JWT issue + a broken verifier that accepts alg=none.
// Do not copy this verifier into anything real. It is wrong on purpose.

const jwt = require("jsonwebtoken");

const SECRET = "lab-secret-do-not-reuse";

function issue(user) {
  // HS256 token with sub = user id. Standard, fine.
  return jwt.sign(
    { sub: user.id, username: user.username, isAdmin: !!user.isAdmin },
    SECRET,
    { algorithm: "HS256", expiresIn: "1h" }
  );
}

// Vulnerable verifier. Two flaws:
//   1. It honours alg=none and returns the payload without checking a signature.
//   2. For everything else, it lets the token's alg header pick the algorithm.
// A real verifier pins algorithms: jwt.verify(token, key, { algorithms: ["HS256"] }).
function verifyBroken(token) {
  if (!token) return null;
  const parts = token.split(".");
  if (parts.length !== 3 && parts.length !== 2) return null;

  let headerJson;
  try {
    headerJson = JSON.parse(
      Buffer.from(parts[0], "base64").toString("utf8")
    );
  } catch (_) {
    return null;
  }

  if (headerJson.alg === "none" || headerJson.alg === "None") {
    // Accept the payload without any signature check. This is the bug.
    try {
      return JSON.parse(Buffer.from(parts[1], "base64").toString("utf8"));
    } catch (_) {
      return null;
    }
  }

  try {
    // No algorithm pin: the verifier trusts the token's alg header.
    return jwt.verify(token, SECRET);
  } catch (_) {
    return null;
  }
}

// Express middleware. Reads Authorization: Bearer <token>.
function authMiddleware(req, _res, next) {
  const h = req.headers.authorization || "";
  const m = h.match(/^Bearer\s+(.+)$/i);
  if (m) {
    req.user = verifyBroken(m[1]);
  }
  next();
}

module.exports = { issue, verifyBroken, authMiddleware, SECRET };

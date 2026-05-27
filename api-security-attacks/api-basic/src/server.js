// api-basic: deliberately vulnerable API server.
// Demonstrates four OWASP API Top 10 (2023) classes in one process:
//   API1 BOLA         GET /users/:id with no ownership check
//   API2 alg=none     /login + a verifier that accepts unsigned tokens
//   API3 mass assign  POST /users binds arbitrary body fields, including isAdmin
//   API4 GraphQL DoS  /graphql with introspection on and no depth limit
//   API5 BFLA (bonus) GET /admin/dump callable without admin check

const express = require("express");
const db = require("./db");
const { issue, authMiddleware } = require("./auth");
const { yoga } = require("./graphql");

const app = express();
app.use(express.json());
app.use(authMiddleware);

// Landing page so curl http://localhost:8088/ gives a 200 and a sane index.
app.get("/", (_req, res) => {
  res.type("text/plain").send(
    [
      "api-basic: deliberately vulnerable API",
      "",
      "Endpoints:",
      "  POST /login                {username, password} -> {token}",
      "  GET  /users                list all users (auth required, no row filter)",
      "  GET  /users/:id            BOLA: any authed user can read any record",
      "  POST /users                Mass assignment: accepts arbitrary fields",
      "  GET  /me                   Returns the caller as decoded from the JWT",
      "  GET  /admin/dump           BFLA: no admin check, dumps all users",
      "  POST /graphql              GraphQL, introspection on, no depth limit",
      "",
      "Lab only. 127.0.0.1 binding required.",
    ].join("\n")
  );
});

// API2 Broken Authentication: login mints a real HS256 JWT.
// The vulnerability is in auth.js (the verifier), not here.
app.post("/login", (req, res) => {
  const { username, password } = req.body || {};
  const user = db.findByUsername(username);
  if (!user || user.password !== password) {
    return res.status(401).json({ error: "invalid credentials" });
  }
  return res.json({ token: issue(user) });
});

// Returns whatever the broken verifier decoded from the bearer token.
app.get("/me", (req, res) => {
  if (!req.user) return res.status(401).json({ error: "no token" });
  return res.json({ user: req.user });
});

// API1 BOLA: any authenticated caller can fetch any user by id.
// The handler never compares :id against req.user.sub.
app.get("/users/:id", (req, res) => {
  if (!req.user) return res.status(401).json({ error: "no token" });
  const u = db.findById(req.params.id);
  if (!u) return res.status(404).json({ error: "not found" });
  return res.json(u);
});

// Helper: list every user (also BOLA-flavoured but mostly here for convenience).
app.get("/users", (req, res) => {
  if (!req.user) return res.status(401).json({ error: "no token" });
  return res.json(db.all());
});

// API3 Mass assignment: spreads req.body straight into a new user record,
// including isAdmin. No allow-list at the boundary.
app.post("/users", (req, res) => {
  const created = db.createMassAssign(req.body || {});
  return res.status(201).json(created);
});

// API5 BFLA: admin-only by intent, but there is no role check.
app.get("/admin/dump", (_req, res) => {
  return res.json({ users: db.all() });
});

// GraphQL with introspection on and no depth/cost limits.
app.use(yoga.graphqlEndpoint, yoga);

const PORT = process.env.PORT || 3000;
app.listen(PORT, "0.0.0.0", () => {
  // eslint-disable-next-line no-console
  console.log(`api-basic listening on :${PORT}`);
});

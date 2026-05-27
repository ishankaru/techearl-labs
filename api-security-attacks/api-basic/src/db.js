// In-memory user store. Seeded with three users.
// Passwords are intentionally weak; this is a deliberately vulnerable lab.

const users = [
  {
    id: 1,
    username: "alice",
    password: "alice123",
    email: "alice@example.com",
    ssn: "111-11-1111",
    isAdmin: false,
  },
  {
    id: 2,
    username: "bob",
    password: "bob123",
    email: "bob@example.com",
    ssn: "222-22-2222",
    isAdmin: false,
  },
  {
    id: 3,
    username: "admin",
    password: "admin123",
    email: "admin@example.com",
    ssn: "999-99-9999",
    isAdmin: true,
  },
];

let nextId = 4;

function findByUsername(username) {
  return users.find((u) => u.username === username);
}

function findById(id) {
  return users.find((u) => u.id === Number(id));
}

function all() {
  return users;
}

// Vulnerable on purpose: spreads whatever fields the caller sent.
function createMassAssign(body) {
  const user = { id: nextId++, ...body };
  users.push(user);
  return user;
}

// Friend graph for the GraphQL nested-query DoS demo.
// Every user is friends with every other user, so the recursion fans out.
function friendsOf(userId) {
  return users.filter((u) => u.id !== Number(userId));
}

module.exports = { findByUsername, findById, all, createMassAssign, friendsOf };

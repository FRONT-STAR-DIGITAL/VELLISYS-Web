#!/usr/bin/env python3
"""Concurrent reverse proxy in front of PHP's single-threaded built-in server."""
from __future__ import annotations

import http.client
import itertools
import socket
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

BACKENDS = [
    ("127.0.0.1", 43230),
    ("127.0.0.1", 43231),
    ("127.0.0.1", 43232),
    ("127.0.0.1", 43233),
]
_cycle = itertools.cycle(BACKENDS)
HOP = {
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
}


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def handle_one(self) -> None:
        host, port = next(_cycle)
        length = int(self.headers.get("Content-Length") or 0)
        body = self.rfile.read(length) if length else b""
        headers = {
            k: v
            for k, v in self.headers.items()
            if k.lower() not in HOP and k.lower() != "host"
        }
        headers["Connection"] = "close"
        headers["Host"] = f"{host}:{port}"
        conn = http.client.HTTPConnection(host, port, timeout=60)
        try:
            conn.request(self.command, self.path, body=body, headers=headers)
            resp = conn.getresponse()
            data = resp.read()
        except Exception as exc:  # noqa: BLE001
            self.send_error(502, f"PHP worker unreachable: {exc}")
            return
        finally:
            conn.close()
        self.send_response(resp.status, resp.reason)
        for key, value in resp.getheaders():
            if key.lower() in HOP:
                continue
            self.send_header(key, value)
        self.send_header("Connection", "close")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(data)

    def do_GET(self) -> None:
        self.handle_one()

    def do_POST(self) -> None:
        self.handle_one()

    def do_HEAD(self) -> None:
        self.handle_one()

    def do_PUT(self) -> None:
        self.handle_one()

    def do_DELETE(self) -> None:
        self.handle_one()

    def do_PATCH(self) -> None:
        self.handle_one()

    def do_OPTIONS(self) -> None:
        self.handle_one()

    def log_message(self, fmt: str, *args: object) -> None:
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))


class DualStackServer(ThreadingHTTPServer):
    address_family = socket.AF_INET6
    allow_reuse_address = True
    daemon_threads = True

    def server_bind(self) -> None:
        self.socket.setsockopt(socket.IPPROTO_IPV6, socket.IPV6_V6ONLY, 0)
        super().server_bind()


def main() -> None:
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 43219
    httpd = DualStackServer(("::", port), Handler)
    print(f"Vellisys proxy on 0.0.0.0:{port} and [::]:{port}", flush=True)
    httpd.serve_forever()


if __name__ == "__main__":
    main()

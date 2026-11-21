#!/usr/bin/env python3
"""Ollama-Attrappe für die Browser-Tests: antwortet sofort und vorhersagbar statt mit einem Sprachmodell.

Kennt die beiden Aufrufe, die das Fewo Toolkit verwendet:
  GET  /api/tags  -> ein installiertes Modell "stub-model"
  POST /api/chat  -> Antwort in mehreren Stücken (JSON-Zeilen wie bei Ollama), die zeigt, was ankam:
                     die Frage, die Zahl der früheren Nachrichten, das Kontextfenster und – bei
                     "Kennst du <Wort>?" – ob das Wort in den Website-Inhalten steht.
Besondere Fragen: "FEHLER" (HTTP 500), "LEER" (leere Antwort), "MARKDOWN" (Antwort mit Markdown).
"""
import json
import re
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

MODEL = "stub-model"


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, *args):
        pass

    def send_json(self, status, data):
        body = json.dumps(data).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == "/api/tags":
            self.send_json(200, {"models": [{"name": MODEL, "model": MODEL}]})
        else:
            self.send_json(404, {"error": "not found"})

    def do_POST(self):
        request = json.loads(self.rfile.read(int(self.headers.get("Content-Length", 0))) or b"{}")
        if self.path != "/api/chat":
            return self.send_json(404, {"error": "not found"})
        if self.headers.get("Authorization", "") not in ("", "Bearer stub-key"):
            return self.send_json(401, {"error": "invalid api key"})
        if request.get("model") != MODEL:
            return self.send_json(404, {"error": f"model '{request.get('model')}' not found"})

        messages = request.get("messages", [])
        system = next((m["content"] for m in messages if m["role"] == "system"), "")
        # Das Paket hängt an die Frage einen Hinweis für das Modell an – für die Antwort zählt nur die Frage
        question, _, note = (messages[-1]["content"] if messages else "").partition("\n\n(Note to the assistant")
        if question == "FEHLER":
            return self.send_json(500, {"error": "stub failure"})

        # Stichwort: "Kennst du <Wort>?" -> steht <Wort> in den Website-Inhalten?
        keyword = re.search(r"Kennst du (.+?)\?", question)
        known = "" if not keyword else (" Bekannt: ja." if keyword.group(1).lower() in system.lower() else " Bekannt: nein.")
        answer = (f"Stub-Antwort auf: {question} (Verlauf: {len(messages) - 2}, Kontext: {'ja' if 'WEBSITE CONTENT' in system else 'nein'}, "
                  f"Hinweis: {'ja' if note else 'nein'}, Fenster: {request.get('options', {}).get('num_ctx')}).{known}")
        if question == "LEER":
            answer = ""
        if question == "MARKDOWN":
            answer = "**Fett** und\n* Punkt eins\n* Punkt zwei"
        if question == "REGELN":
            answer = system[:300]

        self.send_response(200)
        self.send_header("Content-Type", "application/x-ndjson")
        self.send_header("Transfer-Encoding", "chunked")
        self.end_headers()
        pieces = re.findall(r"\S+\s*|\s+", answer) or [""]
        for index, piece in enumerate(pieces):
            self.chunk({"model": MODEL, "message": {"role": "assistant", "content": piece}, "done": False})
            if index == 0:
                time.sleep(0.15)  # die Antwort kommt erkennbar in Stücken
        self.chunk({"model": MODEL, "message": {"role": "assistant", "content": ""}, "done": True, "done_reason": "stop"})
        self.wfile.write(b"0\r\n\r\n")

    def chunk(self, event):
        line = (json.dumps(event) + "\n").encode()
        self.wfile.write(f"{len(line):x}\r\n".encode() + line + b"\r\n")
        self.wfile.flush()


if __name__ == "__main__":
    ThreadingHTTPServer(("0.0.0.0", 11434), Handler).serve_forever()

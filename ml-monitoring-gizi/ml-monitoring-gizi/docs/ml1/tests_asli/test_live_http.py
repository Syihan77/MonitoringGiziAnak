"""Menjalankan uvicorn sungguhan dari folder artefak (tanpa notebook) dan mengakses lewat HTTP."""
import json
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

import pytest

ART = Path(__file__).resolve().parents[1]


def _port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


@pytest.fixture(scope="module")
def base():
    port = _port()
    proc = subprocess.Popen([sys.executable, "-m", "uvicorn", "main:app", "--host", "127.0.0.1", "--port", str(port)],
                            cwd=ART, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    url = f"http://127.0.0.1:{port}"
    for _ in range(60):
        try:
            urllib.request.urlopen(url + "/health", timeout=1)
            break
        except Exception:
            time.sleep(0.5)
    else:
        proc.kill()
        pytest.fail("uvicorn tidak berhasil start")
    yield url
    proc.terminate()
    proc.wait(timeout=10)


def _post(url, body):
    req = urllib.request.Request(url, data=json.dumps(body).encode(), headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=5) as r:
            return r.status, json.load(r)
    except urllib.error.HTTPError as e:
        return e.code, json.load(e)


def test_http_health_dan_predict(base):
    with urllib.request.urlopen(base + "/health", timeout=5) as r:
        assert r.status == 200 and json.load(r)["model_loaded"] is True
    sample = json.loads((ART / "sample_request.json").read_text())
    status, j = _post(base + "/predict", sample)
    assert status == 200 and j["status"] == "ok"
    assert j == json.loads((ART / "sample_response.json").read_text())  # contoh response = output aktual
    status, j = _post(base + "/predict", {"jk": "L"})
    assert status == 422 and j["kode"] == "input_tidak_valid"

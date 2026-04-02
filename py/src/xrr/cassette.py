"""FileCassette — YAML-based on-disk cassette storage."""
from __future__ import annotations

import os
from datetime import datetime, timezone
from typing import Any

import yaml


class CassetteMiss(Exception):
    """Raised when replay finds no matching cassette file."""


class FileCassette:
    """Stores interactions as YAML files in a directory."""

    def __init__(self, directory: str) -> None:
        self._dir = directory

    def save(
        self,
        adapter_id: str,
        fingerprint: str,
        req: dict[str, Any],
        resp: dict[str, Any],
    ) -> None:
        now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
        self._write(adapter_id, fingerprint, "req", now, req)
        self._write(adapter_id, fingerprint, "resp", now, resp)

    def _write(
        self,
        adapter_id: str,
        fingerprint: str,
        kind: str,
        recorded_at: str,
        payload: dict[str, Any],
    ) -> None:
        envelope = {
            "xrr": "1",
            "adapter": adapter_id,
            "fingerprint": fingerprint,
            "recorded_at": recorded_at,
            "payload": payload,
        }
        path = os.path.join(
            self._dir, f"{adapter_id}-{fingerprint}.{kind}.yaml"
        )
        with open(path, "w", encoding="utf-8") as fh:
            yaml.safe_dump(envelope, fh, default_flow_style=False, sort_keys=False)

    def load(
        self, adapter_id: str, fingerprint: str
    ) -> tuple[dict[str, Any], dict[str, Any]]:
        """Return (req_payload, resp_payload). Raises CassetteMiss if not found."""
        req = self._read(adapter_id, fingerprint, "req")
        resp = self._read(adapter_id, fingerprint, "resp")
        return req, resp

    def _read(self, adapter_id: str, fingerprint: str, kind: str) -> dict[str, Any]:
        path = os.path.join(
            self._dir, f"{adapter_id}-{fingerprint}.{kind}.yaml"
        )
        if not os.path.exists(path):
            raise CassetteMiss(
                f"xrr: cassette miss: {adapter_id}-{fingerprint}.{kind}.yaml"
            )
        with open(path, encoding="utf-8") as fh:
            envelope = yaml.safe_load(fh)
        payload = envelope.get("payload")
        if payload is None:
            raise ValueError(f"xrr: missing payload in {kind} file")
        return payload

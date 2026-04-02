"""redis adapter — fingerprints on command + args."""
from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass, field
from typing import Any


@dataclass
class RedisRequest:
    command: str
    args: list[str] = field(default_factory=list)


@dataclass
class RedisResponse:
    result: Any = None


class RedisAdapter:
    id = "redis"

    def fingerprint(self, req: RedisRequest) -> str:
        parts = [req.command.upper()] + list(req.args)
        canonical = json.dumps(" ".join(parts), sort_keys=True, separators=(",", ":"))
        return hashlib.sha256(canonical.encode()).hexdigest()[:8]

    def serialize_req(self, req: RedisRequest) -> dict[str, Any]:
        return {"command": req.command, "args": req.args}

    def serialize_resp(self, resp: RedisResponse) -> dict[str, Any]:
        return {"result": resp.result}

    def deserialize_req(self, data: dict[str, Any]) -> RedisRequest:
        return RedisRequest(command=data["command"], args=data.get("args", []))

    def deserialize_resp(self, data: dict[str, Any]) -> RedisResponse:
        return RedisResponse(result=data.get("result"))

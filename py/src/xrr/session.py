"""Session — record/replay/passthrough dispatcher."""
from __future__ import annotations

from typing import Any, Callable

from .cassette import CassetteMiss, FileCassette

RECORD = "record"
REPLAY = "replay"
PASSTHROUGH = "passthrough"


class Session:
    """Dispatches interactions via record, replay, or passthrough."""

    def __init__(self, mode: str, cassette: FileCassette) -> None:
        if mode not in (RECORD, REPLAY, PASSTHROUGH):
            raise ValueError(f"xrr: unknown mode {mode!r}")
        self._mode = mode
        self._cassette = cassette

    def record(self, adapter: Any, req: Any, do: Callable[[], Any]) -> Any:
        """Execute one interaction according to the session mode.

        record:      call do(), save req+resp, return resp.
        replay:      load cassette, deserialize resp, return; do() NOT called.
        passthrough: call do(), never touch cassette.
        """
        if self._mode == RECORD:
            return self._do_record(adapter, req, do)
        if self._mode == REPLAY:
            return self._do_replay(adapter, req)
        # passthrough
        return do()

    def _do_record(self, adapter: Any, req: Any, do: Callable[[], Any]) -> Any:
        resp = do()
        fp = adapter.fingerprint(req)
        self._cassette.save(
            adapter.id,
            fp,
            adapter.serialize_req(req),
            adapter.serialize_resp(resp),
        )
        return resp

    def _do_replay(self, adapter: Any, req: Any) -> Any:
        fp = adapter.fingerprint(req)
        _req_data, resp_data = self._cassette.load(adapter.id, fp)
        return adapter.deserialize_resp(resp_data)

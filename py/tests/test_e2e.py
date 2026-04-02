"""E2E tests — record + replay cycle for all four adapters.

US-0101 record-first-cassette
US-0102 replay-in-ci
US-0104 adapter-selection
US-0105 cassette-miss
"""
from __future__ import annotations

import subprocess

import pytest

from xrr import CassetteMiss, FileCassette, RECORD, REPLAY, Session
from xrr.adapters.exec import ExecAdapter, ExecRequest, ExecResponse
from xrr.adapters.http import HttpAdapter, HttpRequest, HttpResponse
from xrr.adapters.redis import RedisAdapter, RedisRequest, RedisResponse
from xrr.adapters.sql import SqlAdapter, SqlRequest, SqlResponse


# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------

def _session(mode: str, tmp_path) -> Session:
    return Session(mode, FileCassette(str(tmp_path)))


# ---------------------------------------------------------------------------
# exec adapter  US-0101 US-0102 US-0104
# ---------------------------------------------------------------------------

class TestExecAdapter:
    """Record + replay a shell command; no real process needed in replay."""

    def test_record_writes_cassette(self, tmp_path):
        """RECORD mode: do() called, cassette written."""
        # US-0101
        adapter = ExecAdapter()
        req = ExecRequest(argv=["echo", "hello"])
        expected = ExecResponse(stdout="hello\n", exit_code=0)

        sess = _session(RECORD, tmp_path)
        result = sess.record(adapter, req, lambda: expected)

        assert result.stdout == "hello\n"
        fp = adapter.fingerprint(req)
        assert (tmp_path / f"exec-{fp}.req.yaml").exists()
        assert (tmp_path / f"exec-{fp}.resp.yaml").exists()

    def test_replay_returns_recorded_response(self, tmp_path):
        """REPLAY mode: do() NOT called; deserialized resp matches original."""
        # US-0102
        adapter = ExecAdapter()
        req = ExecRequest(argv=["echo", "hello"])
        original = ExecResponse(stdout="hello\n", stderr="", exit_code=0, duration_ms=5)

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)

        called = []
        result = _session(REPLAY, tmp_path).record(
            adapter, req, lambda: called.append(1) or original
        )

        assert not called, "do() must not run in replay mode"
        assert isinstance(result, ExecResponse)
        assert result.stdout == "hello\n"
        assert result.exit_code == 0

    def test_replay_miss_raises(self, tmp_path):
        """REPLAY mode: unknown request raises CassetteMiss."""
        # US-0105
        with pytest.raises(CassetteMiss):
            _session(REPLAY, tmp_path).record(
                ExecAdapter(), ExecRequest(argv=["no-such-cmd"]), lambda: None
            )

    def test_exec_real_command(self, tmp_path):
        """RECORD real process; REPLAY without running it again."""
        # US-0101 US-0102
        adapter = ExecAdapter()
        req = ExecRequest(argv=["printf", "world"])

        def _run() -> ExecResponse:
            r = subprocess.run(req.argv, capture_output=True, text=True)
            return ExecResponse(
                stdout=r.stdout, stderr=r.stderr, exit_code=r.returncode
            )

        recorded = _session(RECORD, tmp_path).record(adapter, req, _run)
        assert recorded.stdout == "world"

        replayed = _session(REPLAY, tmp_path).record(adapter, req, _run)
        assert replayed.stdout == "world"
        assert replayed.exit_code == 0


# ---------------------------------------------------------------------------
# http adapter  US-0101 US-0102 US-0104
# ---------------------------------------------------------------------------

class TestHttpAdapter:
    """Record + replay an HTTP request; no live server required."""

    def test_record_writes_cassette(self, tmp_path):
        # US-0101
        adapter = HttpAdapter()
        req = HttpRequest(method="GET", url="https://example.com/api/ping")
        resp = HttpResponse(status=200, body='{"ok":true}')

        sess = _session(RECORD, tmp_path)
        result = sess.record(adapter, req, lambda: resp)

        assert result.status == 200
        fp = adapter.fingerprint(req)
        assert (tmp_path / f"http-{fp}.req.yaml").exists()
        assert (tmp_path / f"http-{fp}.resp.yaml").exists()

    def test_replay_returns_recorded_response(self, tmp_path):
        # US-0102
        adapter = HttpAdapter()
        req = HttpRequest(method="POST", url="https://api.example.com/v1/items",
                          body='{"name":"x"}')
        original = HttpResponse(status=201, headers={"content-type": "application/json"},
                                body='{"id":42}')

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)

        called = []
        result = _session(REPLAY, tmp_path).record(
            adapter, req, lambda: called.append(1) or original
        )

        assert not called
        assert isinstance(result, HttpResponse)
        assert result.status == 201
        assert result.body == '{"id":42}'
        assert result.headers.get("content-type") == "application/json"

    def test_replay_miss_raises(self, tmp_path):
        # US-0105
        req = HttpRequest(method="DELETE", url="https://example.com/missing")
        with pytest.raises(CassetteMiss):
            _session(REPLAY, tmp_path).record(HttpAdapter(), req, lambda: None)

    def test_different_methods_produce_different_fingerprints(self, tmp_path):
        """GET and POST to same URL must not collide."""
        # US-0104
        adapter = HttpAdapter()
        get_req = HttpRequest(method="GET", url="https://api.example.com/users")
        post_req = HttpRequest(method="POST", url="https://api.example.com/users",
                               body='{"name":"alice"}')

        assert adapter.fingerprint(get_req) != adapter.fingerprint(post_req)


# ---------------------------------------------------------------------------
# redis adapter  US-0101 US-0102 US-0104
# ---------------------------------------------------------------------------

class TestRedisAdapter:
    """Record + replay a Redis command; no Redis server required."""

    def test_record_writes_cassette(self, tmp_path):
        # US-0101
        adapter = RedisAdapter()
        req = RedisRequest(command="GET", args=["mykey"])
        resp = RedisResponse(result="myvalue")

        sess = _session(RECORD, tmp_path)
        result = sess.record(adapter, req, lambda: resp)

        assert result.result == "myvalue"
        fp = adapter.fingerprint(req)
        assert (tmp_path / f"redis-{fp}.req.yaml").exists()
        assert (tmp_path / f"redis-{fp}.resp.yaml").exists()

    def test_replay_returns_recorded_response(self, tmp_path):
        # US-0102
        adapter = RedisAdapter()
        req = RedisRequest(command="HGET", args=["myhash", "field1"])
        original = RedisResponse(result="value1")

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)

        called = []
        result = _session(REPLAY, tmp_path).record(
            adapter, req, lambda: called.append(1) or original
        )

        assert not called
        assert isinstance(result, RedisResponse)
        assert result.result == "value1"

    def test_replay_list_result(self, tmp_path):
        """Result can be a list (e.g. LRANGE)."""
        # US-0102
        adapter = RedisAdapter()
        req = RedisRequest(command="LRANGE", args=["mylist", "0", "-1"])
        original = RedisResponse(result=["a", "b", "c"])

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)
        result = _session(REPLAY, tmp_path).record(adapter, req, lambda: None)

        assert result.result == ["a", "b", "c"]

    def test_replay_miss_raises(self, tmp_path):
        # US-0105
        with pytest.raises(CassetteMiss):
            _session(REPLAY, tmp_path).record(
                RedisAdapter(), RedisRequest(command="GET", args=["ghost"]), lambda: None
            )


# ---------------------------------------------------------------------------
# sql adapter  US-0101 US-0102 US-0104
# ---------------------------------------------------------------------------

class TestSqlAdapter:
    """Record + replay a SQL query; no DB required."""

    def test_record_writes_cassette(self, tmp_path):
        # US-0101
        adapter = SqlAdapter()
        req = SqlRequest(query="SELECT id, name FROM users WHERE id = ?", args=[1])
        resp = SqlResponse(rows=[{"id": 1, "name": "Alice"}], affected=0)

        sess = _session(RECORD, tmp_path)
        result = sess.record(adapter, req, lambda: resp)

        assert result.rows == [{"id": 1, "name": "Alice"}]
        fp = adapter.fingerprint(req)
        assert (tmp_path / f"sql-{fp}.req.yaml").exists()
        assert (tmp_path / f"sql-{fp}.resp.yaml").exists()

    def test_replay_returns_recorded_response(self, tmp_path):
        # US-0102
        adapter = SqlAdapter()
        req = SqlRequest(query="INSERT INTO orders (item) VALUES (?)", args=["widget"])
        original = SqlResponse(rows=[], affected=1)

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)

        called = []
        result = _session(REPLAY, tmp_path).record(
            adapter, req, lambda: called.append(1) or original
        )

        assert not called
        assert isinstance(result, SqlResponse)
        assert result.affected == 1
        assert result.rows == []

    def test_query_normalization_matches(self, tmp_path):
        """Whitespace-equivalent queries must hit the same cassette."""
        # US-0104
        adapter = SqlAdapter()
        req1 = SqlRequest(query="SELECT  *  FROM  t", args=[])
        req2 = SqlRequest(query="select * from t", args=[])

        assert adapter.fingerprint(req1) == adapter.fingerprint(req2)

    def test_replay_miss_raises(self, tmp_path):
        # US-0105
        with pytest.raises(CassetteMiss):
            _session(REPLAY, tmp_path).record(
                SqlAdapter(),
                SqlRequest(query="SELECT 1", args=[]),
                lambda: None,
            )

    def test_replay_multiple_rows(self, tmp_path):
        """Multi-row result round-trips intact."""
        # US-0102
        adapter = SqlAdapter()
        req = SqlRequest(query="SELECT id, name FROM users", args=[])
        rows = [{"id": 1, "name": "Alice"}, {"id": 2, "name": "Bob"}]
        original = SqlResponse(rows=rows, affected=0)

        _session(RECORD, tmp_path).record(adapter, req, lambda: original)
        result = _session(REPLAY, tmp_path).record(adapter, req, lambda: None)

        assert result.rows == rows
        assert result.affected == 0

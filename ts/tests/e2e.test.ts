/**
 * e2e adapter tests — exec, http, redis, sql.
 *
 * Stories: US-0101, US-0102, US-0104, US-0105
 *
 * Pattern per adapter:
 *   1. Record: Session(record) + synthetic do_() → writes cassette to tmp dir.
 *   2. Replay: Session(replay) from same dir → response matches; do_() NOT called.
 *   3. Miss: Session(replay) with unknown request → throws ErrCassetteMiss.
 */
import fs from "node:fs";
import { spawnSync } from "node:child_process";
import { describe, expect, test, vi } from "vitest";
import { FileCassette } from "../src/cassette.js";
import { FileSession } from "../src/session.js";
import { ErrCassetteMiss } from "../src/xrr.js";
import { ExecAdapter } from "../src/adapters/exec.js";
import { HttpAdapter } from "../src/adapters/http.js";
import { RedisAdapter } from "../src/adapters/redis.js";
import { SqlAdapter } from "../src/adapters/sql.js";

function tmpDir(): string {
  return fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-e2e-");
}

// ---------------------------------------------------------------------------
// exec adapter
// US-0101, US-0102, US-0104
// ---------------------------------------------------------------------------

describe("e2e — exec adapter", () => {
  const adapter = new ExecAdapter();
  const req = { argv: ["echo", "hello"], stdin: "" };
  const resp = { stdout: "hello\n", stderr: "", exit_code: 0, duration_ms: 5 };

  test("record writes cassette files", async () => {
    const dir = tmpDir();
    const session = new FileSession("record", new FileCassette(dir));
    const do_ = vi.fn(async () => resp);

    const result = await session.record(adapter, req, do_);

    expect(do_).toHaveBeenCalledOnce();
    expect(result).toEqual(resp);
    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(2); // req.yaml + resp.yaml
  });

  test("replay returns recorded response; do_() NOT called", async () => {
    const dir = tmpDir();
    // seed cassette via record
    const recSession = new FileSession("record", new FileCassette(dir));
    await recSession.record(adapter, req, async () => resp);

    const repSession = new FileSession("replay", new FileCassette(dir));
    const do_ = vi.fn(async () => ({ stdout: "should-not-run", stderr: "", exit_code: 1 }));

    const result = await repSession.record(adapter, req, do_);

    expect(do_).not.toHaveBeenCalled();
    expect(result.stdout).toBe(resp.stdout);
    expect(result.exit_code).toBe(resp.exit_code);
  });

  // US-0105
  test("replay throws ErrCassetteMiss for unknown request", async () => {
    const dir = tmpDir();
    const session = new FileSession("replay", new FileCassette(dir));
    const unknown = { argv: ["cat", "/no/such/file"] };

    await expect(
      session.record(adapter, unknown, async () => ({ stdout: "", exit_code: 1 }))
    ).rejects.toThrow(ErrCassetteMiss);
  });

  // US-0101 US-0102 — real subprocess round-trip
  test("real subprocess: record actual output then replay without re-running", async () => {
    const dir = tmpDir();
    const execReq = { argv: ["echo", "hello"] };

    const recSession = new FileSession("record", new FileCassette(dir));
    const recorded = await recSession.record(adapter, execReq, async () => {
      const r = spawnSync(execReq.argv[0], execReq.argv.slice(1), { encoding: "utf8" });
      return { stdout: r.stdout, stderr: r.stderr ?? "", exit_code: r.status ?? 0 };
    });

    // real `echo hello` produces "hello\n"
    expect(recorded.stdout).toBe("hello\n");
    expect(recorded.exit_code).toBe(0);

    const called: number[] = [];
    const repSession = new FileSession("replay", new FileCassette(dir));
    const replayed = await repSession.record(adapter, execReq, async () => {
      called.push(1);
      return { stdout: "should-not-run", stderr: "", exit_code: 99 };
    });

    expect(called).toHaveLength(0);
    expect(replayed.stdout).toBe("hello\n");
    expect(replayed.exit_code).toBe(0);
  });
});

// ---------------------------------------------------------------------------
// http adapter
// US-0101, US-0102, US-0104
// ---------------------------------------------------------------------------

describe("e2e — http adapter", () => {
  const adapter = new HttpAdapter();
  const req = {
    method: "GET",
    url: "https://api.example.com/v1/users?page=1",
    headers: { accept: "application/json" },
  };
  const resp = {
    status: 200,
    headers: { "content-type": "application/json" },
    body: '{"users":[]}',
  };

  test("record writes cassette files", async () => {
    const dir = tmpDir();
    const session = new FileSession("record", new FileCassette(dir));
    const do_ = vi.fn(async () => resp);

    const result = await session.record(adapter, req, do_);

    expect(do_).toHaveBeenCalledOnce();
    expect(result.status).toBe(200);
    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(2);
  });

  test("replay returns recorded response; do_() NOT called", async () => {
    const dir = tmpDir();
    const recSession = new FileSession("record", new FileCassette(dir));
    await recSession.record(adapter, req, async () => resp);

    const repSession = new FileSession("replay", new FileCassette(dir));
    const do_ = vi.fn(async () => ({ status: 500 }));

    const result = await repSession.record(adapter, req, do_);

    expect(do_).not.toHaveBeenCalled();
    expect(result.status).toBe(200);
    expect(result.body).toBe('{"users":[]}');
  });

  // US-0105
  test("replay throws ErrCassetteMiss for unknown request", async () => {
    const dir = tmpDir();
    const session = new FileSession("replay", new FileCassette(dir));
    const unknown = { method: "POST", url: "https://api.example.com/v1/orders" };

    await expect(
      session.record(adapter, unknown, async () => ({ status: 201 }))
    ).rejects.toThrow(ErrCassetteMiss);
  });

  // US-0104 — different methods produce different fingerprints (no cassette collision)
  test("GET and POST to same URL produce different fingerprints", async () => {
    const httpAdapter = new HttpAdapter();
    const getReq = { method: "GET", url: "https://api.example.com/users" };
    const postReq = { method: "POST", url: "https://api.example.com/users", body: '{"name":"alice"}' };

    const fpGet = await httpAdapter.fingerprint(getReq);
    const fpPost = await httpAdapter.fingerprint(postReq);

    expect(fpGet).not.toBe(fpPost);
  });
});

// ---------------------------------------------------------------------------
// redis adapter
// US-0101, US-0102, US-0104
// ---------------------------------------------------------------------------

describe("e2e — redis adapter", () => {
  const adapter = new RedisAdapter();
  const req = { command: "GET", args: ["session:42"] };
  const resp = { result: "token-abc123" };

  test("record writes cassette files", async () => {
    const dir = tmpDir();
    const session = new FileSession("record", new FileCassette(dir));
    const do_ = vi.fn(async () => resp);

    const result = await session.record(adapter, req, do_);

    expect(do_).toHaveBeenCalledOnce();
    expect(result.result).toBe("token-abc123");
    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(2);
  });

  test("replay returns recorded response; do_() NOT called", async () => {
    const dir = tmpDir();
    const recSession = new FileSession("record", new FileCassette(dir));
    await recSession.record(adapter, req, async () => resp);

    const repSession = new FileSession("replay", new FileCassette(dir));
    const do_ = vi.fn(async () => ({ result: null }));

    const result = await repSession.record(adapter, req, do_);

    expect(do_).not.toHaveBeenCalled();
    expect(result.result).toBe("token-abc123");
  });

  // US-0105
  test("replay throws ErrCassetteMiss for unknown request", async () => {
    const dir = tmpDir();
    const session = new FileSession("replay", new FileCassette(dir));
    const unknown = { command: "SET", args: ["session:99", "value"] };

    await expect(
      session.record(adapter, unknown, async () => ({ result: "OK" }))
    ).rejects.toThrow(ErrCassetteMiss);
  });

  // US-0102 — list result (LRANGE-style) round-trips intact
  test("list result (LRANGE) round-trips intact", async () => {
    const dir = tmpDir();
    const redisAdapter = new RedisAdapter();
    const lrangeReq = { command: "LRANGE", args: ["mylist", "0", "-1"] };
    const original = { result: ["a", "b", "c"] };

    await new FileSession("record", new FileCassette(dir)).record(
      redisAdapter, lrangeReq, async () => original
    );
    const result = await new FileSession("replay", new FileCassette(dir)).record(
      redisAdapter, lrangeReq, async () => ({ result: null })
    );

    expect(result.result).toEqual(["a", "b", "c"]);
  });
});

// ---------------------------------------------------------------------------
// sql adapter
// US-0101, US-0102, US-0104
// ---------------------------------------------------------------------------

describe("e2e — sql adapter", () => {
  const adapter = new SqlAdapter();
  const req = { query: "SELECT id, name FROM users WHERE id = $1", args: [42] };
  const resp = { rows: [{ id: 42, name: "Alice" }], affected: 0 };

  test("record writes cassette files", async () => {
    const dir = tmpDir();
    const session = new FileSession("record", new FileCassette(dir));
    const do_ = vi.fn(async () => resp);

    const result = await session.record(adapter, req, do_);

    expect(do_).toHaveBeenCalledOnce();
    expect(result.rows).toHaveLength(1);
    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(2);
  });

  test("replay returns recorded response; do_() NOT called", async () => {
    const dir = tmpDir();
    const recSession = new FileSession("record", new FileCassette(dir));
    await recSession.record(adapter, req, async () => resp);

    const repSession = new FileSession("replay", new FileCassette(dir));
    const do_ = vi.fn(async () => ({ rows: [], affected: 0 }));

    const result = await repSession.record(adapter, req, do_);

    expect(do_).not.toHaveBeenCalled();
    expect(result.rows).toEqual([{ id: 42, name: "Alice" }]);
  });

  // US-0105
  test("replay throws ErrCassetteMiss for unknown request", async () => {
    const dir = tmpDir();
    const session = new FileSession("replay", new FileCassette(dir));
    const unknown = { query: "DELETE FROM sessions WHERE expired = true" };

    await expect(
      session.record(adapter, unknown, async () => ({ affected: 5 }))
    ).rejects.toThrow(ErrCassetteMiss);
  });

  // US-0104 — query normalization: same query with different whitespace/case → same fingerprint
  test("whitespace and case-variant queries share one fingerprint", async () => {
    const sqlAdapter = new SqlAdapter();
    const req1 = { query: "SELECT  *  FROM  t", args: [] };
    const req2 = { query: "select * from t", args: [] };

    expect(await sqlAdapter.fingerprint(req1)).toBe(await sqlAdapter.fingerprint(req2));
  });

  // US-0102 — multi-row result round-trips intact
  test("multi-row result round-trips intact", async () => {
    const dir = tmpDir();
    const sqlAdapter = new SqlAdapter();
    const multiReq = { query: "SELECT id, name FROM users", args: [] };
    const rows = [{ id: 1, name: "Alice" }, { id: 2, name: "Bob" }];
    const original = { rows, affected: 0 };

    await new FileSession("record", new FileCassette(dir)).record(
      sqlAdapter, multiReq, async () => original
    );
    const result = await new FileSession("replay", new FileCassette(dir)).record(
      sqlAdapter, multiReq, async () => ({ rows: [], affected: 0 })
    );

    expect(result.rows).toEqual(rows);
    expect(result.affected).toBe(0);
  });
});

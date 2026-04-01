# XRR — Cross-Runtime Recorder Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan
> task-by-task.

**Goal:** Build `xrr` — a generic multi-channel interaction recorder/replayer with a
pluggable adapter interface — in Go (reference), then port to TypeScript, Python, PHP,
and Rust.

**Architecture:** A thin core defines three concepts: `Cassette` (storage), `Adapter`
(channel plugin), and `Session` (record/replay lifecycle). Each language ships the core
+ a stdlib of built-in adapters (exec, http, grpc, redis, sql). The cassette format is
language-agnostic YAML/JSON so cassettes recorded in one language replay in another.

**Tech Stack:** Go 1.24 (reference impl) · TypeScript (Node 22, ESM) · Python 3.12
(uv) · PHP 8.3 (Composer) · Rust 1.78 (Cargo)

---

## Vocabulary

| Term | Meaning |
|------|---------|
| **Cassette** | On-disk store of recorded interactions (one dir per session) |
| **Interaction** | One request+response pair (argv+stdout, HTTP req+resp, etc.) |
| **Fingerprint** | SHA256[:8] of the interaction's key fields → cassette filename |
| **Adapter** | Plugin that knows how to intercept one channel type |
| **Session** | Scoped record-or-replay context; owns cassette + active adapters |
| **Mode** | `record` · `replay` · `passthrough` |

---

## Cassette File Format (language-agnostic)

```
<session-dir>/
  <adapter-id>-<fingerprint>.req.yaml   ← serialized request
  <adapter-id>-<fingerprint>.resp.yaml  ← serialized response
```

Fingerprint: `sha256(canonical(request))[:8]` where canonical = sorted-key JSON of
the fields that uniquely identify the interaction (adapter-defined).

Request/response envelope (both files share this wrapper):

```yaml
xrr: "1"                    # format version
adapter: exec               # adapter id
fingerprint: "a3f9c1b2"
recorded_at: "2026-04-01T12:00:00Z"
# adapter-specific payload follows:
payload:
  argv: ["gh", "pr", "view", "123"]
  stdin: ""
  env: {}
```

Response envelope adds:

```yaml
payload:
  stdout: "..."
  stderr: ""
  exit_code: 0
  duration_ms: 142
```

---

## Core Interface (Go — reference)

```go
// Adapter intercepts one channel type.
type Adapter interface {
    ID() string                            // e.g. "exec", "http", "redis"
    Fingerprint(req Request) (string, error)
    Serialize(v any) ([]byte, error)
    Deserialize(data []byte, target any) error
}

// Request is an opaque adapter-defined struct.
type Request interface{ adapterID() string }

// Response is an opaque adapter-defined struct.
type Response interface{ adapterID() string }

// Session owns the lifecycle of one record/replay run.
type Session interface {
    Record(ctx context.Context, req Request,
           do func() (Response, error)) (Response, error)
    Close() error
}

// Cassette reads/writes interaction files.
type Cassette interface {
    Load(adapterID, fingerprint string, target any) error
    Save(adapterID, fingerprint string, req, resp any) error
}
```

---

## Adapters (all languages)

| ID | Request key fields | Response fields |
|----|--------------------|----------------|
| `exec` | argv, stdin, env-subset | stdout, stderr, exit_code |
| `http` | method, url, headers-subset, body | status, headers, body |
| `grpc` | service, method, message | status_code, message |
| `redis` | command, args | result |
| `sql` | query (normalized), args | rows, affected |

---

## Project Layout

```
xrr/
  go/          ← Go reference impl (module: hop.top/xrr)
  ts/          ← TypeScript port   (package: @hop-top/xrr)
  py/          ← Python port       (package: xrr)
  php/         ← PHP port          (package: hop-top/xrr)
  rs/          ← Rust port         (crate: xrr)
  spec/        ← language-agnostic cassette format spec + fixture cassettes
  README.md
```

---

## Task 1: Repo + spec scaffold

**Files:**
- Create: `xrr/README.md`
- Create: `xrr/spec/cassette-format-v1.md`
- Create: `xrr/spec/fixtures/exec-happy/exec-a3f9c1b2.req.yaml`
- Create: `xrr/spec/fixtures/exec-happy/exec-a3f9c1b2.resp.yaml`

**Step 1: Init git repo**

```bash
cd ~/.w/ideacrafterslabs/xrr
git init
echo "# xrr" > README.md
```

**Step 2: Write cassette format spec**

`spec/cassette-format-v1.md` — document the envelope schema above verbatim.
Include: version field, adapter ID rules (`[a-z][a-z0-9-]*`), fingerprint algorithm,
file naming convention, required vs optional fields.

**Step 3: Write fixture cassettes**

Two YAML files under `spec/fixtures/exec-happy/` using the envelope schema above.
These are the cross-language conformance fixtures — every port must be able to replay
them without modification.

**Step 4: Commit**

```bash
git add spec/ README.md
git commit -m "chore: scaffold repo + cassette format spec v1"
```

---

## Task 2: Go — core interfaces + cassette

**Files:**
- Create: `go/go.mod` (module `hop.top/xrr`)
- Create: `go/xrr.go` — exported types: `Adapter`, `Request`, `Response`, `Session`,
  `Cassette`, `Mode`
- Create: `go/cassette.go` — `FileCassette` impl
- Create: `go/cassette_test.go`

**Step 1: Write failing test**

```go
// cassette_test.go
func TestFileCassetteSaveLoad(t *testing.T) {
    dir := t.TempDir()
    c := xrr.NewFileCassette(dir)
    req := map[string]any{"argv": []string{"gh", "pr", "view", "1"}}
    resp := map[string]any{"stdout": "title: foo", "exit_code": 0}
    fp := "a3f9c1b2"
    require.NoError(t, c.Save("exec", fp, req, resp))
    var gotReq, gotResp map[string]any
    require.NoError(t, c.Load("exec", fp, &gotReq, &gotResp))
    assert.Equal(t, req, gotReq)
    assert.Equal(t, resp, gotResp)
}
```

**Step 2: Run to verify FAIL**

```bash
cd go && go test ./... 2>&1 | head -20
# Expected: compile error — types not defined
```

**Step 3: Implement `xrr.go` interfaces + `cassette.go`**

`FileCassette.Save`: marshal req+resp into envelope YAML, write two files.
`FileCassette.Load`: read two files, unmarshal payloads into targets.
Envelope must include `xrr: "1"`, `adapter`, `fingerprint`, `recorded_at`.

**Step 4: Run to verify PASS**

```bash
cd go && go test ./... -v
```

**Step 5: Commit**

```bash
git add go/
git commit -m "feat(go): core interfaces + FileCassette"
```

---

## Task 3: Go — Session (record + replay)

**Files:**
- Create: `go/session.go` — `FileSession` impl
- Create: `go/session_test.go`

**Step 1: Write failing tests**

```go
func TestSessionRecord(t *testing.T) {
    dir := t.TempDir()
    s := xrr.NewSession(xrr.ModeRecord, xrr.NewFileCassette(dir))
    adapter := &fakeAdapter{id: "exec"}
    req := &fakeReq{key: "argv=echo+hello"}
    called := false
    resp, err := s.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
        called = true
        return &fakeResp{out: "hello\n"}, nil
    })
    require.NoError(t, err)
    assert.True(t, called)
    assert.Equal(t, "hello\n", resp.(*fakeResp).out)
    // cassette file must exist
    entries, _ := os.ReadDir(dir)
    assert.Len(t, entries, 2) // req + resp
}

func TestSessionReplay(t *testing.T) {
    dir := t.TempDir()
    // seed cassette
    c := xrr.NewFileCassette(dir)
    c.Save("exec", "a3f9c1b2", fakeReqPayload, fakeRespPayload)

    s := xrr.NewSession(xrr.ModeReplay, c)
    adapter := &fakeAdapter{id: "exec", fp: "a3f9c1b2"}
    req := &fakeReq{key: "argv=echo+hello"}
    called := false
    resp, err := s.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
        called = true // must NOT be called in replay
        return nil, nil
    })
    require.NoError(t, err)
    assert.False(t, called)
    _ = resp
}

func TestSessionReplayMiss(t *testing.T) {
    dir := t.TempDir()
    s := xrr.NewSession(xrr.ModeReplay, xrr.NewFileCassette(dir))
    adapter := &fakeAdapter{id: "exec", fp: "deadbeef"}
    req := &fakeReq{}
    _, err := s.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
        return nil, nil
    })
    require.ErrorIs(t, err, xrr.ErrCassetteMiss)
}
```

**Step 2: Run to verify FAIL**

```bash
cd go && go test ./... 2>&1 | head -20
```

**Step 3: Implement `session.go`**

`FileSession.Record`:
- mode=record → call do(), serialize req+resp via adapter, Save to cassette
- mode=replay → Fingerprint(req), Load from cassette, deserialize; if miss →
  `ErrCassetteMiss`
- mode=passthrough → always call do(), never touch cassette

Export sentinel: `var ErrCassetteMiss = errors.New("xrr: cassette miss")`

**Step 4: Run to verify PASS**

```bash
cd go && go test ./... -v
```

**Step 5: Commit**

```bash
git commit -m "feat(go): Session with record/replay/passthrough modes"
```

---

## Task 4: Go — exec adapter

**Files:**
- Create: `go/adapters/exec/exec.go`
- Create: `go/adapters/exec/exec_test.go`

**Step 1: Write failing test**

```go
func TestExecAdapterFingerprint(t *testing.T) {
    a := exec.NewAdapter()
    req := &exec.Request{Argv: []string{"gh", "pr", "view", "1"}, Stdin: ""}
    fp, err := a.Fingerprint(req)
    require.NoError(t, err)
    assert.Len(t, fp, 8)
    // deterministic
    fp2, _ := a.Fingerprint(req)
    assert.Equal(t, fp, fp2)
    // different argv → different fp
    req2 := &exec.Request{Argv: []string{"gh", "pr", "view", "2"}}
    fp3, _ := a.Fingerprint(req2)
    assert.NotEqual(t, fp, fp3)
}

func TestExecAdapterRoundtrip(t *testing.T) {
    a := exec.NewAdapter()
    req := &exec.Request{Argv: []string{"echo", "hello"}, Stdin: ""}
    data, err := a.Serialize(req)
    require.NoError(t, err)
    var got exec.Request
    require.NoError(t, a.Deserialize(data, &got))
    assert.Equal(t, req.Argv, got.Argv)
}
```

**Step 2: Run to verify FAIL**

```bash
cd go && go test ./adapters/exec/... 2>&1 | head -10
```

**Step 3: Implement exec adapter**

```go
type Request struct {
    Argv  []string          `yaml:"argv"`
    Stdin string            `yaml:"stdin,omitempty"`
    Env   map[string]string `yaml:"env,omitempty"`
}
type Response struct {
    Stdout   string `yaml:"stdout"`
    Stderr   string `yaml:"stderr,omitempty"`
    ExitCode int    `yaml:"exit_code"`
    DurationMs int64 `yaml:"duration_ms,omitempty"`
}
```

Fingerprint: `sha256(json.Marshal({argv, stdin}))` → hex[:8].

**Step 4: Run to verify PASS**

```bash
cd go && go test ./... -v
```

**Step 5: Commit**

```bash
git commit -m "feat(go/adapters): exec adapter"
```

---

## Task 5: Go — http, grpc, redis, sql adapters (same pattern)

One task per adapter; each follows the exact same steps as Task 4.
Key fields for fingerprinting:

| Adapter | Request fingerprint fields |
|---------|---------------------------|
| `http` | method + url (path+query, no host) + sha256(body)[:8] |
| `grpc` | service + method + sha256(proto-bytes)[:8] |
| `redis` | command + args joined |
| `sql` | normalized query (strip whitespace, lowercase) + args |

Commit each adapter separately:
```bash
git commit -m "feat(go/adapters): http adapter"
git commit -m "feat(go/adapters): grpc adapter"
git commit -m "feat(go/adapters): redis adapter"
git commit -m "feat(go/adapters): sql adapter"
```

---

## Task 6: Go — cross-language conformance test

**Files:**
- Create: `go/conformance_test.go`

**Step 1: Write test**

```go
// TestConformanceFixtures replays spec/fixtures cassettes — proves Go can read
// cassettes produced by any other language port.
func TestConformanceFixtures(t *testing.T) {
    fixtures := "../../spec/fixtures"
    entries, err := os.ReadDir(fixtures)
    require.NoError(t, err)
    for _, e := range entries {
        if !e.IsDir() { continue }
        t.Run(e.Name(), func(t *testing.T) {
            c := xrr.NewFileCassette(filepath.Join(fixtures, e.Name()))
            s := xrr.NewSession(xrr.ModeReplay, c)
            // each fixture dir ships a manifest.yaml listing adapter+fp pairs
            // load manifest, replay each, assert no ErrCassetteMiss
            runFixture(t, s, filepath.Join(fixtures, e.Name()))
        })
    }
}
```

**Step 2: Write `spec/fixtures/exec-happy/manifest.yaml`**

```yaml
interactions:
  - adapter: exec
    fingerprint: a3f9c1b2
```

**Step 3: Run to verify PASS**

```bash
cd go && go test ./... -run TestConformance -v
```

**Step 4: Commit**

```bash
git commit -m "test(go): cross-language conformance fixture test"
```

---

## Task 7: TypeScript port

**Files:**
- Create: `ts/package.json` (name: `@hop-top/xrr`, type: module)
- Create: `ts/src/xrr.ts` — interfaces: `Adapter`, `Request`, `Response`, `Session`,
  `Cassette`, `Mode`
- Create: `ts/src/cassette.ts` — `FileCassette`
- Create: `ts/src/session.ts` — `FileSession`
- Create: `ts/src/adapters/exec.ts`
- Create: `ts/src/adapters/http.ts`
- Create: `ts/src/adapters/redis.ts`
- Create: `ts/src/adapters/sql.ts`
- Create: `ts/src/index.ts` — barrel export
- Create: `ts/tests/cassette.test.ts`
- Create: `ts/tests/session.test.ts`
- Create: `ts/tests/conformance.test.ts`

**Step 1: Init**

```bash
cd ts && pnpm init && pnpm add -D typescript vitest @types/node js-yaml
```

**Step 2: Port interfaces**

TypeScript interface mirrors Go exactly:

```typescript
export type Mode = "record" | "replay" | "passthrough";
export const ErrCassetteMiss = new Error("xrr: cassette miss");

export interface Adapter<Req, Resp> {
    id: string;
    fingerprint(req: Req): Promise<string>;
    serializeReq(req: Req): unknown;
    serializeResp(resp: Resp): unknown;
    deserializeReq(data: unknown): Req;
    deserializeResp(data: unknown): Resp;
}

export interface Session {
    record<Req, Resp>(
        adapter: Adapter<Req, Resp>,
        req: Req,
        do_: () => Promise<Resp>
    ): Promise<Resp>;
}
```

**Step 3: Port FileCassette + FileSession** (same logic as Go, using `js-yaml` + `fs/promises`)

**Step 4: Port exec + http + redis + sql adapters**

Fingerprint: `crypto.createHash('sha256').update(canonical).digest('hex').slice(0,8)`

**Step 5: Write + run tests**

```bash
cd ts && pnpm vitest run
```

**Step 6: Conformance test** — reads `../../spec/fixtures`, same logic as Go.

**Step 7: Commit**

```bash
git commit -m "feat(ts): TypeScript port with full adapter set + conformance"
```

---

## Task 8: Python port

**Files:**
- Create: `py/pyproject.toml` (name: `xrr`, Python ≥ 3.12)
- Create: `py/src/xrr/__init__.py`
- Create: `py/src/xrr/cassette.py`
- Create: `py/src/xrr/session.py`
- Create: `py/src/xrr/adapters/exec.py`
- Create: `py/src/xrr/adapters/http.py`
- Create: `py/src/xrr/adapters/redis.py`
- Create: `py/src/xrr/adapters/sql.py`
- Create: `py/tests/test_cassette.py`
- Create: `py/tests/test_session.py`
- Create: `py/tests/test_conformance.py`

**Step 1: Init**

```bash
cd py && uv init --name xrr && uv add pyyaml && uv add --dev pytest
```

**Step 2: Port interfaces as Protocol (structural typing)**

```python
from typing import Protocol, TypeVar, Generic, Callable, Awaitable
Req = TypeVar("Req")
Resp = TypeVar("Resp")

class Adapter(Protocol[Req, Resp]):
    id: str
    def fingerprint(self, req: Req) -> str: ...
    def serialize_req(self, req: Req) -> dict: ...
    def serialize_resp(self, resp: Resp) -> dict: ...
    def deserialize_req(self, data: dict) -> Req: ...
    def deserialize_resp(self, data: dict) -> Resp: ...

class CassetteMiss(Exception): pass
```

**Step 3: Port FileCassette + Session** (sync; use `yaml.safe_dump`/`safe_load`)

**Step 4: Port adapters** — same fingerprint algorithm: `hashlib.sha256`

**Step 5: Write + run tests**

```bash
cd py && uv run pytest -v
```

**Step 6: Conformance test** — reads `../../spec/fixtures`

**Step 7: Commit**

```bash
git commit -m "feat(py): Python port with full adapter set + conformance"
```

---

## Task 9: PHP port

**Files:**
- Create: `php/composer.json` (name: `hop-top/xrr`, PHP ≥ 8.3)
- Create: `php/src/AdapterInterface.php`
- Create: `php/src/Cassette.php`
- Create: `php/src/Session.php`
- Create: `php/src/Mode.php` (enum)
- Create: `php/src/Adapters/ExecAdapter.php`
- Create: `php/src/Adapters/HttpAdapter.php`
- Create: `php/src/Adapters/RedisAdapter.php`
- Create: `php/src/Adapters/SqlAdapter.php`
- Create: `php/src/Exception/CassetteMissException.php`
- Create: `php/tests/CassetteTest.php`
- Create: `php/tests/SessionTest.php`
- Create: `php/tests/ConformanceTest.php`

**Step 1: Init**

```bash
cd php && composer init --name hop-top/xrr --require "php:>=8.3"
composer require --dev phpunit/phpunit symfony/yaml
```

**Step 2: Port interfaces**

```php
interface AdapterInterface {
    public function getId(): string;
    public function fingerprint(mixed $req): string;
    public function serializeReq(mixed $req): array;
    public function serializeResp(mixed $resp): array;
    public function deserializeReq(array $data): mixed;
    public function deserializeResp(array $data): mixed;
}

enum Mode: string {
    case Record      = 'record';
    case Replay      = 'replay';
    case Passthrough = 'passthrough';
}
```

**Step 3: Port Cassette + Session** (use `symfony/yaml`)

**Step 4: Port adapters** — fingerprint: `substr(hash('sha256', $canonical), 0, 8)`

**Step 5: Write + run tests**

```bash
cd php && ./vendor/bin/phpunit tests/
```

**Step 6: Conformance test**

**Step 7: Commit**

```bash
git commit -m "feat(php): PHP port with full adapter set + conformance"
```

---

## Task 10: Rust port

**Files:**
- Create: `rs/Cargo.toml` (name: `xrr`, edition 2021)
- Create: `rs/src/lib.rs` — re-exports
- Create: `rs/src/cassette.rs`
- Create: `rs/src/session.rs`
- Create: `rs/src/error.rs`
- Create: `rs/src/adapters/exec.rs`
- Create: `rs/src/adapters/http.rs`
- Create: `rs/src/adapters/redis.rs`
- Create: `rs/src/adapters/sql.rs`
- Create: `rs/tests/conformance.rs`

**Step 1: Init**

```bash
cd rs && cargo init --lib
cargo add serde serde_yaml sha2 hex thiserror
cargo add --dev tempfile
```

**Step 2: Port interfaces as traits**

```rust
pub trait Adapter: Send + Sync {
    type Req: Serialize + DeserializeOwned + Send;
    type Resp: Serialize + DeserializeOwned + Send;

    fn id(&self) -> &str;
    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError>;
}

#[derive(Debug, thiserror::Error)]
pub enum XrrError {
    #[error("xrr: cassette miss for adapter={adapter} fp={fingerprint}")]
    CassetteMiss { adapter: String, fingerprint: String },
    #[error("xrr: io error: {0}")]
    Io(#[from] std::io::Error),
    #[error("xrr: serde error: {0}")]
    Serde(#[from] serde_yaml::Error),
}
```

**Step 3: Port FileCassette + Session** (sync; use `serde_yaml`)

**Step 4: Port adapters** — fingerprint: `sha2::Sha256`, hex-encode, take 8 chars

**Step 5: Write + run tests**

```bash
cd rs && cargo test
```

**Step 6: Conformance test**

```rust
#[test]
fn test_conformance_fixtures() {
    let fixtures = Path::new("../../spec/fixtures");
    for entry in fs::read_dir(fixtures).unwrap() { ... }
}
```

**Step 7: Commit**

```bash
git commit -m "feat(rs): Rust port with full adapter set + conformance"
```

---

## Task 11: CI + README

**Files:**
- Create: `.github/workflows/ci.yml`
- Modify: `README.md`

**Step 1: CI matrix**

```yaml
jobs:
  go:  { uses: actions/setup-go, run: cd go && go test ./... }
  ts:  { uses: actions/setup-node, run: cd ts && pnpm vitest run }
  py:  { uses: actions/setup-python, run: cd py && uv run pytest }
  php: { uses: shivammathur/setup-php, run: cd php && phpunit tests/ }
  rs:  { uses: actions-rs/toolchain, run: cd rs && cargo test }
```

All jobs include the conformance step (reads `spec/fixtures`).

**Step 2: README**

Sections: what is xrr · quick example (Go) · adapter list · cassette format link ·
porting guide (how to add a new language) · cassette cross-compat guarantee.

**Step 3: Commit**

```bash
git commit -m "ci: matrix CI for all 5 languages + README"
```

---

## Sequencing

```
Task 1  (spec)
  └── Task 2 (go core)
        └── Task 3 (go session)
              ├── Task 4 (go exec adapter)
              ├── Task 5 (go http/grpc/redis/sql)
              └── Task 6 (go conformance)
                    ├── Task 7 (ts)
                    ├── Task 8 (py)
                    ├── Task 9 (php)
                    └── Task 10 (rs)
                          └── Task 11 (ci + readme)
```

Tasks 7–10 are independent after Task 6 passes; run in parallel if possible.

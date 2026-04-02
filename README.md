# xrr — Cross-Runtime Recorder

Generic multi-channel interaction recorder/replayer with a pluggable adapter interface.

## What is xrr?

`xrr` records and replays interactions across any channel type (exec, HTTP, gRPC, Redis, SQL).
Cassettes are language-agnostic YAML — record in Go, replay in Python, or any other port.

Three modes:
- **record** — intercept real calls, write cassettes
- **replay** — serve cassettes, never touch the network
- **passthrough** — calls go through, cassette untouched

## Quick Example (Go)

```go
// Record once
s := xrr.NewSession(xrr.ModeRecord, xrr.NewFileCassette("./cassettes"))
adapter := exec.NewAdapter()
resp, err := s.Record(ctx, adapter, &exec.Request{
    Argv: []string{"gh", "pr", "view", "123"},
}, func() (xrr.Response, error) {
    return runCommand(...)
})

// Replay everywhere — real command never runs
s2 := xrr.NewSession(xrr.ModeReplay, xrr.NewFileCassette("./cassettes"))
resp2, err := s2.Record(ctx, adapter, req, do)
```

## Adapters

| ID    | Intercepts      | Fingerprint fields                          |
|-------|-----------------|---------------------------------------------|
| exec  | shell commands  | argv + stdin                                |
| http  | HTTP requests   | method + path+query + sha256(body)[:8]      |
| grpc  | gRPC calls      | service + method + sha256(proto-bytes)[:8]  |
| redis | Redis commands  | command + args                              |
| sql   | SQL queries     | normalized query + args                     |

## Cassette Format

Language-agnostic YAML envelope. See [spec/cassette-format-v1.md](spec/cassette-format-v1.md).

```
cassettes/
  exec-a3f9c1b2.req.yaml
  exec-a3f9c1b2.resp.yaml
```

Cross-compat guarantee: cassettes recorded in any language replay in any other.
Every port runs the shared conformance fixtures from `spec/fixtures/`.

## Languages

| Dir  | Package       | Test command          |
|------|---------------|-----------------------|
| go/  | hop.top/xrr   | `go test ./...`       |
| ts/  | @hop-top/xrr  | `pnpm vitest run`     |
| py/  | xrr           | `uv run pytest -v`    |
| php/ | hop-top/xrr   | `phpunit tests/`      |
| rs/  | xrr (crate)   | `cargo test`          |

## Porting Guide

To add a new language:

1. **Implement `Adapter`** — `id`, `fingerprint(req)`, `serialize`/`deserialize`
2. **Implement `FileCassette`** — `save(adapterID, fp, req, resp)`, `load(adapterID, fp)`
   - Write YAML envelopes: `xrr:"1"`, `adapter`, `fingerprint`, `recorded_at`, `payload`
   - File naming: `<adapter>-<fingerprint>.<req|resp>.yaml`
3. **Implement `Session`** — dispatch record/replay/passthrough
   - replay miss → raise/return `ErrCassetteMiss`
4. **Run conformance** — point at `spec/fixtures/`, load every `manifest.yaml` interaction
5. Add a job to `.github/workflows/ci.yml`

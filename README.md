# xrr — Cross-Runtime Recorder

Generic multi-channel interaction recorder/replayer with a pluggable adapter interface.

## What is xrr?

`xrr` records and replays interactions across any channel type (exec, HTTP, gRPC, Redis, SQL).
Cassettes are language-agnostic YAML — record in Go, replay in Python.

## Quick Example (Go)

```go
s := xrr.NewSession(xrr.ModeRecord, xrr.NewFileCassette("./cassettes"))
resp, err := s.Record(ctx, exec.Adapter, &exec.Request{
    Argv: []string{"gh", "pr", "view", "123"},
}, func() (xrr.Response, error) {
    return runCommand(...)
})
```

Replay later:

```go
s := xrr.NewSession(xrr.ModeReplay, xrr.NewFileCassette("./cassettes"))
// same Record() call — real command never runs
```

## Adapters

| ID    | Intercepts             |
|-------|------------------------|
| exec  | shell commands         |
| http  | HTTP requests          |
| grpc  | gRPC calls             |
| redis | Redis commands         |
| sql   | SQL queries            |

## Cassette Format

Language-agnostic YAML envelope. See [spec/cassette-format-v1.md](spec/cassette-format-v1.md).

Cross-compat guarantee: cassettes recorded in any language replay in any other.

## Porting Guide

1. Implement `Adapter` interface (id, fingerprint, serialize/deserialize)
2. Implement `FileCassette` (save/load YAML envelopes)
3. Implement `Session` (record/replay/passthrough dispatch)
4. Run conformance fixtures from `spec/fixtures/` — all must pass

## Languages

| Dir | Package          | Status |
|-----|------------------|--------|
| go/ | hop.top/xrr      | ref    |
| ts/ | @hop-top/xrr     | port   |
| py/ | xrr              | port   |
| php/| hop-top/xrr      | port   |
| rs/ | xrr              | port   |

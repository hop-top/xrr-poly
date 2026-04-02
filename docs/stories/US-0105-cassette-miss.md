# User Story: Handle Cassette Miss in Replay Mode

**System:** xrr
**Personas:** [Solo Developer](../personas/solo-developer.md)

---

## User Goal

As a Solo Developer, I want a clear, actionable error when a cassette is missing in
replay mode so I know exactly which interaction needs to be recorded.

---

## Context

Developer adds a new code path that makes a call not yet recorded. CI runs in replay
mode and hits the new call — they need to know what to record, not a cryptic error.

---

## Acceptance Criteria

- [ ] Missing cassette in replay mode returns `ErrCassetteMiss` (not a panic).
- [ ] Error message includes: adapter ID, fingerprint, and expected cassette file path.
- [ ] `do` function is never executed on a miss (replay mode is strict).
- [ ] Passthrough mode: on miss, `do` is executed and result returned (no error).

---

## Implementation Notes

```pseudocode
// Replay mode, cassette missing
session = NewSession(REPLAY, cassette)
resp, err = session.Record(ctx, http_adapter, new_req, do)
// err = ErrCassetteMiss{adapter:"http", fingerprint:"b2d4e6f8",
//                        path:"testdata/cassettes/http-b2d4e6f8.resp.yaml"}
// do() never called

// Passthrough mode, cassette missing → do() executes
session_pt = NewSession(PASSTHROUGH, cassette)
resp, err = session_pt.Record(ctx, http_adapter, new_req, do)
// do() called; result returned; no error
```

### Key Files

- `go/session.go`: `replay()` — miss detection + `ErrCassetteMiss`
- Language-specific error types in each port

---

## E2E / Verification Checklist

- [ ] Remove cassette; run in replay mode; verify `ErrCassetteMiss` returned.
- [ ] Error message contains adapter ID, fingerprint, expected path.
- [ ] Instrument `do` with counter; verify count=0 on miss in replay mode.
- [ ] Run in passthrough with missing cassette; verify `do` called, no error.

---

## Related Stories

- [[US-0102]](./US-0102-replay-in-ci.md) — Replay in CI
- [[US-0101]](./US-0101-record-first-cassette.md) — Record first cassette

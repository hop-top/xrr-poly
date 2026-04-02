# User Story: Replay Cassettes in CI Without Infra

**System:** xrr
**Personas:** [Solo Developer](../personas/solo-developer.md)

---

## User Goal

As a Solo Developer, I want my tests to replay cassettes in CI without running any
external services so that CI is fast, free, and never flaky due to network issues.

---

## Context

Developer commits cassettes to VCS. CI has no Redis, no Postgres, no external APIs.
Tests must pass using only the recorded cassette files.

---

## Acceptance Criteria

- [ ] `xrr.NewSession(ModeReplay, FileCassette(dir))` replays without executing `do`.
- [ ] Response returned matches the recorded cassette payload exactly.
- [ ] `do` function is never called in replay mode.
- [ ] If cassette file missing: returns `ErrCassetteMiss` (not a panic or silent wrong result).
- [ ] Mode switchable via env var (e.g., `XRR_MODE=replay`) without code change.

---

## Implementation Notes

```pseudocode
// CI: no services running
session = NewSession(mode=REPLAY, cassette=FileCassette("testdata/cassettes"))
resp = session.Record(ctx, http_adapter, req, func():
    panic("should not be called")
)
// resp loaded from cassette; do() never executed
```

### Key Files

- `go/session.go`: `replay()` path — load cassette, skip `do`
- `go/cassette.go`: `FileCassette.Load()`

---

## E2E / Verification Checklist

- [ ] Set `XRR_MODE=replay`; run tests; verify `do` never called (instrument with counter).
- [ ] Remove a cassette file; verify `ErrCassetteMiss` returned, not panic.
- [ ] Verify cassette payload fields match original recorded values exactly.
- [ ] Run full suite in replay mode with no network; all assertions pass.

---

## Related Stories

- [[US-0101]](./US-0101-record-first-cassette.md) — Record first cassette
- [[US-0105]](./US-0105-cassette-miss.md) — Handle cassette miss in replay mode

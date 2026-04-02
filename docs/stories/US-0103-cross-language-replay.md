# User Story: Replay Go Cassette in Python Test

**System:** xrr
**Personas:** [Solo Developer](../personas/solo-developer.md),
[Test Infrastructure Engineer](../personas/test-infrastructure-engineer.md)

---

## User Goal

As a developer with a polyglot stack, I want a cassette recorded in Go to replay
correctly in Python (and vice versa) so that cross-language integration tests
share one cassette set.

---

## Context

A Go service records an exec interaction. A Python evaluation script needs to replay
the same cassette. The cassette format is the cross-language contract — no conversion
or re-recording needed.

---

## Acceptance Criteria

- [ ] Cassette recorded in any port replays in any other port unchanged.
- [ ] Fingerprint algorithm produces identical bytes across all languages for the same
      canonical input.
- [ ] `recorded_at` and other envelope fields preserved without modification during replay.
- [ ] All ports pass `spec/fixtures/` conformance suite (cross-language replay proof).

---

## Implementation Notes

```pseudocode
// Go: record exec interaction
session_go = NewSession(RECORD, FileCassette("cassettes/"))
session_go.Record(ctx, exec_adapter, ExecRequest(argv=["gh","pr","view","42"]), run)
// cassettes/exec-a3f9c1b2.req.yaml written

// Python: replay same cassette, different runtime
session_py = Session(mode=Mode.REPLAY, cassette=FileCassette("cassettes/"))
resp = session_py.record(exec_adapter, ExecRequest(argv=["gh","pr","view","42"]), do)
// loads cassettes/exec-a3f9c1b2.req.yaml — same fingerprint
```

### Key Files

- `spec/cassette-format-v1.md`: fingerprint algorithm + encoding rules
- `spec/fixtures/`: conformance fixtures (cross-language replay test cases)
- All ports' `conformance_test.*` files

---

## E2E / Verification Checklist

- [ ] Record exec cassette in Go; replay in Python — response matches.
- [ ] Record HTTP cassette in TypeScript; replay in Rust — response matches.
- [ ] All ports pass `spec/fixtures/exec-happy` conformance test.
- [ ] Fingerprint bytes identical across Go/Python/TS/PHP/Rust for same input.

---

## Related Stories

- [[US-0101]](./US-0101-record-first-cassette.md) — Record first cassette
- [[US-0302]](./US-0302-port-adapter.md) — Port an adapter to a new language

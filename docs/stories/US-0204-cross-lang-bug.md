# User Story: Debug Cross-Language Cassette Mismatch

**System:** xrr
**Personas:** [OSS Contributor](../personas/oss-contributor.md)

---

## User Goal

As an OSS Contributor, I want to diagnose and fix a fingerprint mismatch between two
language ports so that cassettes recorded in one language replay correctly in another.

---

## Context

Record in Go produces fingerprint `a3f9c1b2`. Python computes `b4e8f2c6` for the same
input. Replay fails with `ErrCassetteMiss`. Root cause: encoding difference in
canonical JSON (key sort order, float precision, Unicode normalization).

---

## Acceptance Criteria

- [ ] `ErrCassetteMiss` error includes computed fingerprint and expected file path
      (actionable debugging info).
- [ ] Fingerprint algorithm documented precisely enough to catch encoding edge cases
      (key sort order, byte encoding, empty field handling).
- [ ] Fix in both ports produces identical fingerprint for identical input.
- [ ] Regression fixture added to `spec/fixtures/` covering the edge case.
- [ ] All ports pass new regression fixture.

---

## Implementation Notes

```pseudocode
// Reproduce: record in Go, replay in Python
go_fp   = sha256(json_sorted({"argv":["gh","pr","view","42"],"stdin":""}))[:8]
// "a3f9c1b2"

py_fp   = sha256(json.dumps({"argv":["gh","pr","view","42"],"stdin":""},
                            sort_keys=True).encode())[:8]
// may differ if json.dumps adds trailing space or float formatting differs

// Fix: both ports use identical canonical encoding
// Add regression fixture:
spec/fixtures/exec-canonical-edge/
  exec-<fp>.req.yaml   // input with empty stdin field
  exec-<fp>.resp.yaml
  manifest.yaml
```

### Key Files

- `spec/cassette-format-v1.md`: fingerprint algorithm — canonical JSON definition
- Fingerprint functions in each port's adapter implementations

---

## E2E / Verification Checklist

- [ ] Record in Go; attempt replay in Python; observe `ErrCassetteMiss` with fingerprint.
- [ ] Print canonical JSON bytes in both languages; identify divergence.
- [ ] Apply fix; verify fingerprints match.
- [ ] Add regression fixture; all ports pass.
- [ ] `task test` green after fix.

---

## Related Stories

- [[US-0203]](./US-0203-add-conformance-fixture.md) — Add conformance fixture
- [[US-0103]](./US-0103-cross-language-replay.md) — Cross-language replay (solo-dev view)

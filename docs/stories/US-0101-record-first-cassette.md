# User Story: Record First Cassette

**System:** xrr
**Personas:** [Solo Developer](../personas/solo-developer.md)

---

## User Goal

As a Solo Developer, I want to wrap a real call in an xrr session and record a cassette
so that I can replay the interaction in tests without hitting the real service again.

---

## Context

Dev is writing a test for code that makes an HTTP call. They want to record the real
response once, commit the cassette, and have CI replay it with no network access.

---

## Acceptance Criteria

- [ ] `xrr.NewSession(ModeRecord, FileCassette(dir))` accepts any supported adapter.
- [ ] Calling `session.Record(ctx, adapter, req, do)` executes `do`, writes cassette files.
- [ ] Two files written: `<adapter>-<fp>.req.yaml` and `<adapter>-<fp>.resp.yaml`.
- [ ] Cassette YAML matches format spec (`xrr: "1"`, `adapter`, `fingerprint`,
      `recorded_at`, `payload`).
- [ ] Second call with same request overwrites cassette (idempotent record).

---

## Implementation Notes

```pseudocode
session = NewSession(mode=RECORD, cassette=FileCassette("testdata/cassettes"))
resp = session.Record(ctx, http_adapter, req, func():
    return http_client.Do(req)
)
// cassette written:
//   testdata/cassettes/http-<fp>.req.yaml
//   testdata/cassettes/http-<fp>.resp.yaml
```

### Key Files

- `go/session.go`: `Record()` dispatch + `record()` write path
- `go/cassette.go`: `FileCassette.Save()`
- `spec/cassette-format-v1.md`: envelope schema

---

## E2E / Verification Checklist

- [ ] Run session in record mode; verify two YAML files created.
- [ ] Open YAML; verify all required envelope fields present.
- [ ] Run second record with same request; verify file overwritten, not duplicated.
- [ ] Run in passthrough mode; verify no cassette files created.

---

## Related Stories

- [[US-0102]](./US-0102-replay-in-ci.md) — Replay cassettes in CI without infra
- [[US-0104]](./US-0104-adapter-selection.md) — Pick the right adapter

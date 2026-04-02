# User Story: Explore Interfaces and Conformance Suite

**System:** xrr
**Personas:** [OSS Contributor](../personas/oss-contributor.md)

---

## User Goal

As an OSS Contributor, I want to understand the core interfaces and conformance test
suite in under an hour so I can confidently port or extend xrr.

---

## Context

Contributor just cloned the repo. They need to find the `Adapter` interface, understand
the cassette format, and see how conformance fixtures prove cross-language correctness —
before writing a single line.

---

## Acceptance Criteria

- [ ] `go/xrr.go` contains documented `Adapter`, `Cassette`, `Session` interfaces.
- [ ] `spec/cassette-format-v1.md` documents fingerprint algorithm, file naming, envelope schema.
- [ ] `spec/fixtures/` contains at least one complete fixture set with `manifest.yaml`.
- [ ] `go test ./...` passes on fresh clone with no external services.
- [ ] Contributing guide (README or `docs/contributing.md`) describes the port checklist.

---

## Implementation Notes

```pseudocode
// Contributor flow
git clone ...
go test ./...         // all green
cat spec/cassette-format-v1.md
cat go/xrr.go         // Adapter interface: 4 methods
cat spec/fixtures/exec-happy/manifest.yaml
// understands: to add an adapter, implement Adapter + add fixture + pass conformance
```

### Key Files

- `go/xrr.go`: interface definitions
- `spec/cassette-format-v1.md`: format contract
- `spec/fixtures/exec-happy/`: reference fixture set
- `README.md`: porting guide section

---

## E2E / Verification Checklist

- [ ] Fresh clone; `go test ./...` green with no setup beyond Go install.
- [ ] Contributor can name all 4 `Adapter` methods after reading `go/xrr.go`.
- [ ] Fingerprint algorithm describable from spec alone (no code reading required).
- [ ] Fixture `manifest.yaml` lists interactions with expected fingerprints.

---

## Related Stories

- [[US-0202]](./US-0202-port-adapter.md) — Port an adapter to a new language
- [[US-0203]](./US-0203-add-conformance-fixture.md) — Add conformance fixture

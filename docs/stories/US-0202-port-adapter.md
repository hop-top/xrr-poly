# User Story: Port an Adapter to a New Language

**System:** xrr
**Personas:** [OSS Contributor](../personas/oss-contributor.md)

---

## User Goal

As an OSS Contributor, I want to implement a new adapter (e.g., Kafka) across one or
more ports so that xrr users on those platforms can record Kafka interactions.

---

## Context

Contributor identifies a missing adapter. They use the Go reference implementation
as the canonical pattern, implement the same interface in the target language, add
conformance fixtures, and verify cross-language replay before opening a PR.

---

## Acceptance Criteria

- [ ] New adapter implements `Adapter` interface: `id()`, `fingerprint(req)`,
      `serialize(v)`, `deserialize(data, target)`.
- [ ] Fingerprint is deterministic and matches Go reference for the same input.
- [ ] Adapter serialize/deserialize round-trips all documented request/response fields.
- [ ] Conformance fixture added: `spec/fixtures/<adapter>-happy/manifest.yaml` +
      `.req.yaml` + `.resp.yaml`.
- [ ] All existing ports pass new conformance fixture without modification.
- [ ] `task test` green across all 5 languages after adding new adapter.

---

## Implementation Notes

```pseudocode
// Go reference: implement Adapter interface
type KafkaAdapter struct{}
func (a *KafkaAdapter) ID() string { return "kafka" }
func (a *KafkaAdapter) Fingerprint(req Request) (string, error):
    r := req.(*KafkaRequest)
    canonical := json.marshal_sorted({topic: r.Topic, partition: r.Partition,
                                      value_sha: sha256(r.Value)[:8]})
    return hex(sha256(canonical))[:8]

// Port to Python
class KafkaAdapter:
    def id(self): return "kafka"
    def fingerprint(self, req): ...  # same algorithm

// Add fixture
spec/fixtures/kafka-happy/
  kafka-<fp>.req.yaml
  kafka-<fp>.resp.yaml
  manifest.yaml
```

### Key Files

- `go/adapters/exec/exec.go`: reference adapter pattern
- `spec/cassette-format-v1.md`: fingerprint algorithm rules
- `spec/fixtures/`: fixture structure

---

## E2E / Verification Checklist

- [ ] New adapter registered and callable in target language.
- [ ] Record Kafka request in Go; replay in Python — response matches.
- [ ] All 5 ports pass `spec/fixtures/kafka-happy` conformance.
- [ ] `task test` green after adding new adapter.
- [ ] PR description links to cassette format spec and interface docs.

---

## Related Stories

- [[US-0201]](./US-0201-explore-interfaces.md) — Explore interfaces
- [[US-0203]](./US-0203-add-conformance-fixture.md) — Add conformance fixture
- [[US-0204]](./US-0204-cross-lang-bug.md) — Debug cross-language cassette mismatch

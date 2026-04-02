# Persona: OSS Contributor (Multi-Language Library Developer)

**Extends:** [contributors/oss-go-developer](~/.w/ideacrafterslabs/.docs/personas/contributors/oss-go-developer.md)

**xrr specialization:** Developer porting xrr to a new language, adding an adapter across
multiple ports, or fixing a cross-language cassette compatibility bug.

---

## Goals

- Understand the Go reference impl and cassette spec in < 1 hour; port or adapt confidently.
- Add a new adapter (e.g., Kafka, GraphQL) across all ports without breaking conformance.
- Get PR merged in ≤ 2 review cycles; conformance suite proves cross-language correctness.

---

## Interaction Pattern

### Explore reference impl + spec

```pseudocode
cat spec/cassette-format-v1.md    // format contract
cat go/xrr.go                     // core interfaces
go test ./...                     // all green before touching anything
```

### Port a new adapter (e.g., Kafka)

```pseudocode
// implement Adapter interface in target language
// fingerprint: topic + partition + sha256(value)[:8]
// serialize/deserialize: YAML payload with topic, partition, offset, value

// add conformance fixture:
spec/fixtures/kafka-happy/kafka-<fp>.req.yaml
spec/fixtures/kafka-happy/kafka-<fp>.resp.yaml
spec/fixtures/kafka-happy/manifest.yaml

// run conformance in all ports:
go test ./...           // passes
uv run pytest -v        // passes
pnpm vitest run         // passes
cargo test              // passes
phpunit tests/          // passes
```

### Fix cross-language fingerprint bug

```pseudocode
// reproduce: record in Go, replay in Python → cassette miss
// bisect: compare fingerprint bytes; find encoding difference
// fix in both ports; add cross-lang fixture to spec/fixtures/
```

---

## Key Pain Points

- Adapter interface must be implemented in 5 languages; inconsistency breaks cassettes.
- Conformance suite is the only cross-language correctness signal — must stay green.
- Fingerprint algorithm differences (encoding, sort order) cause silent replay misses.

---

## System Leverage

### Conformance fixtures as contract

`spec/fixtures/` is the source of truth; any port passing all fixtures is
cassette-compatible. Contributor adds fixtures for new adapters as part of the PR.

### Small, stable interface

`Adapter` has 4 methods; `Session` has 2. Porting is bounded scope, not framework
rewrite.

### Taskfile for cross-language gate

```pseudocode
task lint    // lint all 5 languages in parallel
task test    // test all 5 languages in parallel
```

---

## User Stories

- [[US-0301]](../stories/US-0301-explore-interfaces.md) — Explore interfaces + conformance suite
- [[US-0302]](../stories/US-0302-port-adapter.md) — Port an adapter to a new language
- [[US-0303]](../stories/US-0303-add-conformance-fixture.md) — Add conformance fixture for new adapter
- [[US-0304]](../stories/US-0304-cross-lang-bug.md) — Debug cross-language cassette mismatch
- [[US-0305]](../stories/US-0305-ci-green.md) — Get all 5 language CI jobs green

---

## Success Metrics

- New adapter merged with passing conformance in all ports within 2 review cycles.
- `task test` green on first attempt after following contribution guide.
- No regression in existing adapters or cross-language replay after merge.

---

## Collaboration with Other Personas

- **Test Infrastructure Engineer:** contributor's new adapter directly unblocks test
  infra engineer (e.g., Kafka adapter for event-driven service tests).
- **AI Agent Developer:** GraphQL or gRPC adapter additions unblock agent tool coverage.
- **Solo Developer:** contributor is often a solo dev who hit a missing adapter.

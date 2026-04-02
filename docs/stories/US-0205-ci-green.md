# User Story: Get All 5 Language CI Jobs Green

**System:** xrr
**Personas:** [OSS Contributor](../personas/oss-contributor.md)

---

## User Goal

As an OSS Contributor, I want all 5 language CI jobs to pass before requesting review
so that my PR demonstrates working cross-language compatibility.

---

## Context

Contributor has implemented a new adapter or fixed a bug. Before opening PR, they run
the full gate locally (`task test`, `task lint`) and push only when green. CI runs the
same jobs in parallel.

---

## Acceptance Criteria

- [ ] `task test` runs all 5 language test suites in parallel; reports per-language result.
- [ ] `task lint` runs all 5 language linters in parallel.
- [ ] CI matrix runs all 5 jobs; PR merge blocked if any fail.
- [ ] Conformance tests run as part of each language's test suite (not separate step).
- [ ] Contributor can identify which language failed from CI output without guessing.

---

## Implementation Notes

```pseudocode
// Local gate before push
task lint    // go vet + eslint + ruff + phpstan + clippy (parallel)
task test    // go test + vitest + pytest + phpunit + cargo test (parallel)

// CI matrix (GitHub Actions)
jobs:
  test-go:   go test ./...
  test-ts:   pnpm vitest run
  test-py:   uv run pytest -v
  test-php:  phpunit tests/
  test-rs:   cargo test
```

### Key Files

- `Taskfile.yml`: parallel lint + test targets
- `.github/workflows/ci.yml`: CI matrix definition

---

## E2E / Verification Checklist

- [ ] `task test` exits 0 with all 5 languages passing.
- [ ] `task lint` exits 0 with all 5 languages clean.
- [ ] Introduce deliberate test failure in one language; verify only that job fails.
- [ ] CI PR check blocks merge when any language job fails.

---

## Related Stories

- [[US-0202]](./US-0202-port-adapter.md) — Port an adapter
- [[US-0203]](./US-0203-add-conformance-fixture.md) — Add conformance fixture

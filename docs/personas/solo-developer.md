# Persona: Solo Developer (Indie Hacker / Side Project)

**Extends:** [individuals/solo-developer](~/.w/ideacrafterslabs/.docs/personas/individuals/solo-developer.md)

**xrr specialization:** Single dev building a small app or CLI tool who needs
deterministic tests without standing up real services or paying per-API-call.

---

## Goals

- Drop xrr into an existing project in < 15 min; record once, commit, never re-record.
- Zero ops: no mock servers, no running Redis/Postgres just to run tests locally.
- One library that handles exec, HTTP, and SQL — not a different mock tool per channel.

---

## Interaction Pattern

### Add xrr + record in dev

```pseudocode
// Go: one import, one session
s := xrr.NewSession(xrr.ModeRecord, xrr.NewFileCassette("testdata/cassettes"))
resp, _ := s.Record(ctx, http.NewAdapter(), req, callRealAPI)
// cassette written to testdata/cassettes/http-<fp>.{req,resp}.yaml
```

### Commit + run tests offline

```pseudocode
git add testdata/cassettes/
XRR_MODE=replay go test ./...
// no network; passes on any machine
```

### Inspect cassette when test fails

```pseudocode
cat testdata/cassettes/http-a3f9c1b2.resp.yaml
// plain YAML; edit status_code to reproduce an error case
```

---

## Key Pain Points

- Per-test mock setup is boilerplate; cassettes written once, reused forever.
- HTTP mock libs don't cover exec commands or SQL — need 3 tools for 3 channels.
- CI runs hit rate limits on free-tier APIs; cassettes eliminate the problem.

---

## System Leverage

### Single session API

One `Session` + pluggable adapters covers all channels — no per-adapter mock setup.

### Human-readable cassettes

Plain YAML; edit by hand to test error paths. No code changes needed.

### Passthrough mode

`ModePassthrough` lets real calls flow during development; switch to `ModeReplay`
for CI via env var.

---

## User Stories

- [[US-0101]](../stories/US-0101-record-first-cassette.md) — Record first cassette
- [[US-0102]](../stories/US-0102-replay-in-ci.md) — Replay in CI without services
- [[US-0104]](../stories/US-0104-adapter-selection.md) — Pick the right adapter
- [[US-0105]](../stories/US-0105-cassette-miss.md) — Understand cassette miss error

---

## Success Metrics

- First cassette recorded and committed in < 15 min.
- Full test suite runs offline on a fresh checkout.
- Zero mock boilerplate — no per-test stub setup.

---

## Collaboration with Other Personas

- **Test Infrastructure Engineer:** solo dev graduates to this persona when project
  grows to a team; patterns carry over directly.
- **OSS Contributor:** solo dev who hits a missing adapter may open a PR to add it.

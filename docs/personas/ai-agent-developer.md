# Persona: AI Agent Developer

**Primary Role:** Developer building LLM-powered agents or agentic pipelines where the
agent invokes external tools (exec, HTTP APIs, SQL, Redis). Needs deterministic, repeatable
test runs without calling real APIs or running real commands.

---

## Goals

- Record real tool call traces once; replay them in tests without API keys or live services.
- Assert that agent logic handles specific responses (errors, edge cases) by manipulating
  cassettes directly.
- Cross-language: agent in Python, evaluation harness in Go — same cassettes work in both.

---

## Interaction Pattern

### Record agent tool calls

```pseudocode
// Python — record gh CLI call made by agent
with xrr.Session(mode=Mode.RECORD, cassette=FileCassette("cassettes/")) as s:
    result = s.record(exec_adapter, ExecRequest(argv=["gh", "pr", "view", "42"]), run)
```

### Replay in evaluation harness (different language)

```pseudocode
// Go — replay same cassette in eval suite
s := xrr.NewSession(xrr.ModeReplay, xrr.NewFileCassette("cassettes/"))
resp, _ := s.Record(ctx, exec.NewAdapter(), &exec.Request{Argv: []string{"gh","pr","view","42"}}, do)
```

### Inject error cassette for failure-path testing

```pseudocode
// Manually edit cassette YAML to set exit_code: 1, stderr: "not found"
// Replay in test — agent must handle tool failure gracefully
```

---

## Key Pain Points

- LLM-driven agent tests are expensive and non-deterministic when hitting real APIs.
- Tool call mocking per-test is brittle; cassettes capture realistic payloads at source.
- Multi-language agent ecosystems (Python agent + Go evaluator) share no mock infra today.

---

## System Leverage

### Record real, replay deterministically

One record run during agent dev; all CI runs replay cassettes — same latency, same
payload, zero API cost.

### Cassette editing for edge cases

Human-readable YAML; adjust `exit_code`, `stdout`, `status` to inject faults or
rare responses without writing mock logic.

### Cross-language replay

Python agent cassettes replay in Go evaluation harness; cassette format is the
contract, not the language.

---

## User Stories

- [[US-0201]](../stories/US-0201-record-tool-call.md) — Record agent tool call trace
- [[US-0202]](../stories/US-0202-replay-in-evaluator.md) — Replay trace in cross-language evaluator
- [[US-0203]](../stories/US-0203-inject-failure.md) — Inject failure via cassette edit
- [[US-0204]](../stories/US-0204-http-tool-cassette.md) — Record HTTP tool call (REST API)
- [[US-0205]](../stories/US-0205-sql-tool-cassette.md) — Record SQL tool call

---

## Success Metrics

- Agent evaluation suite runs offline; no API keys needed in CI.
- Edge-case scenarios (tool errors, empty results) covered via cassette injection.
- Cross-language eval harness replays Python-recorded cassettes without modification.

---

## Collaboration with Other Personas

- **Test Infrastructure Engineer:** shares cassette-in-VCS pattern; AI agent developer
  extends it with LLM-specific replay needs.
- **Solo Developer:** may be building a personal agent project; adopts xrr for
  local dev iteration speed (no API spend per run).

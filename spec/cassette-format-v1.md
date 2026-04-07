# Cassette Format v1

Spec for the xrr on-disk cassette format. Language-agnostic; all ports MUST conform.

## Directory Layout

```
<session-dir>/
  <adapter-id>-<fingerprint>.req.yaml
  <adapter-id>-<fingerprint>.resp.yaml
```

## Adapter ID Rules

- Pattern: `[a-z][a-z0-9-]*`
- Examples: `exec`, `http`, `grpc`, `redis`, `sql`

## Fingerprint Algorithm

```
fingerprint = sha256(canonical(request))[:8]
```

Where `canonical(request)` = deterministic JSON with sorted keys of the fields
that uniquely identify the interaction (adapter-defined).

Result: 8 lowercase hex characters, e.g. `a3f9c1b2`.

## File Naming

```
<adapter-id>-<fingerprint>.req.yaml   ← serialized request
<adapter-id>-<fingerprint>.resp.yaml  ← serialized response
```

## Envelope Schema

Both `.req.yaml` and `.resp.yaml` share this wrapper:

```yaml
xrr: "1"                      # format version — required; always string "1"
adapter: exec                 # adapter id — required
fingerprint: "a3f9c1b2"       # 8-char hex — required
recorded_at: "2026-04-01T12:00:00Z"  # RFC3339 UTC — required
payload:                      # adapter-specific — required, MUST be an object
  <adapter fields>
```

### Required Fields (both req and resp)

| Field        | Type   | Description                        |
|--------------|--------|------------------------------------|
| xrr          | string | Format version, always `"1"`       |
| adapter      | string | Adapter ID matching `[a-z][a-z0-9-]*` |
| fingerprint  | string | 8 hex chars                        |
| recorded_at  | string | RFC3339 UTC timestamp              |
| payload      | object | Adapter-specific request/response. MUST be a non-null object (writers MUST normalize an absent or null payload to `{}`). |

### Optional Fields (`.resp.yaml` only)

| Field | Type   | Description                                                                                                                                                                                                                                                                                       |
|-------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| error | string | Recorded error message from the original interaction. If present and non-empty, replay MUST re-emit a non-nil error alongside the response payload. Empty or absent ⇒ success. Recordings written before this field existed replay as success. **`.req.yaml` MUST NOT carry this field.** |

Any other additional top-level fields are ignored by loaders (forward compat).

## Request Envelope Example (exec)

```yaml
xrr: "1"
adapter: exec
fingerprint: "a3f9c1b2"
recorded_at: "2026-04-01T12:00:00Z"
payload:
  argv: ["gh", "pr", "view", "123"]
  stdin: ""
  env: {}
```

## Response Envelope Example (exec, success)

```yaml
xrr: "1"
adapter: exec
fingerprint: "a3f9c1b2"
recorded_at: "2026-04-01T12:00:00Z"
payload:
  stdout: "title: My PR\n"
  stderr: ""
  exit_code: 0
  duration_ms: 142
```

## Response Envelope Example (exec, failure)

```yaml
xrr: "1"
adapter: exec
fingerprint: "deadbeef"
recorded_at: "2026-04-01T12:00:00Z"
error: "exit status 1"
payload:
  stdout: ""
  stderr: "boom\n"
  exit_code: 1
  duration_ms: 8
```

On replay, the session re-emits a non-nil error whose `Error()` string equals
the recorded `error` field, alongside the deserialized response payload.

## Cross-Language Conformance

All language ports MUST be able to replay cassettes written by any other port.
Conformance fixtures live in `spec/fixtures/`. Each fixture dir contains:
- `*.req.yaml` + `*.resp.yaml` pairs
- `manifest.yaml` listing all `adapter`+`fingerprint` pairs to replay

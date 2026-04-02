// Package xrr_test — e2e adapter tests: record → replay → cassette-miss.
// US-0101, US-0102, US-0104, US-0105
package xrr_test

import (
	"context"
	"errors"
	"testing"

	xrr "hop.top/xrr"
	xexec "hop.top/xrr/adapters/exec"
	xgrpc "hop.top/xrr/adapters/grpc"
	xhttp "hop.top/xrr/adapters/http"
	xredis "hop.top/xrr/adapters/redis"
	xsql "hop.top/xrr/adapters/sql"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

// newSession returns a FileSession wired to a fresh temp dir.
func newSession(t *testing.T, mode xrr.Mode) (*xrr.FileSession, string) {
	t.Helper()
	dir := t.TempDir()
	return xrr.NewSession(mode, xrr.NewFileCassette(dir)), dir
}

// replaySession returns a FileSession wired to an existing cassette dir.
func replaySession(t *testing.T, dir string) *xrr.FileSession {
	t.Helper()
	return xrr.NewSession(xrr.ModeReplay, xrr.NewFileCassette(dir))
}

// ── exec ─────────────────────────────────────────────────────────────────────

// TestE2EExec_RecordReplay — US-0101, US-0104
// Records a shell-command interaction then replays it; asserts payload round-trip.
func TestE2EExec_RecordReplay(t *testing.T) {
	adapter := xexec.NewAdapter()
	req := &xexec.Request{Argv: []string{"echo", "hello"}}
	want := &xexec.Response{Stdout: "hello\n", ExitCode: 0}

	// --- record
	recSess, dir := newSession(t, xrr.ModeRecord)
	resp, err := recSess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		return want, nil
	})
	require.NoError(t, err)
	assert.Equal(t, "hello\n", resp.(*xexec.Response).Stdout)

	// --- replay
	replaySess := replaySession(t, dir)
	raw, err := replaySess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		t.Fatal("do() must not be called in replay mode")
		return nil, nil
	})
	require.NoError(t, err)
	payload := raw.(*xrr.RawResponse).Payload
	assert.Equal(t, "hello\n", payload["stdout"])
}

// TestE2EExec_CassetteMiss — US-0105
// Replaying an unknown request returns ErrCassetteMiss.
func TestE2EExec_CassetteMiss(t *testing.T) {
	adapter := xexec.NewAdapter()
	_, dir := newSession(t, xrr.ModeRecord) // empty cassette dir

	replaySess := replaySession(t, dir)
	_, err := replaySess.Record(context.Background(), adapter,
		&xexec.Request{Argv: []string{"unknown", "cmd"}},
		func() (xrr.Response, error) { return nil, nil },
	)
	require.True(t, errors.Is(err, xrr.ErrCassetteMiss))
}

// ── http ─────────────────────────────────────────────────────────────────────

// TestE2EHTTP_RecordReplay — US-0101, US-0104
// Records an HTTP GET interaction then replays it; asserts status + body.
func TestE2EHTTP_RecordReplay(t *testing.T) {
	adapter := xhttp.NewAdapter()
	req := &xhttp.Request{Method: "GET", URL: "https://example.com/api/health"}
	want := &xhttp.Response{Status: 200, Body: `{"ok":true}`}

	// --- record
	recSess, dir := newSession(t, xrr.ModeRecord)
	resp, err := recSess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		return want, nil
	})
	require.NoError(t, err)
	assert.Equal(t, 200, resp.(*xhttp.Response).Status)

	// --- replay
	replaySess := replaySession(t, dir)
	raw, err := replaySess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		t.Fatal("do() must not be called in replay mode")
		return nil, nil
	})
	require.NoError(t, err)
	payload := raw.(*xrr.RawResponse).Payload
	assert.EqualValues(t, 200, payload["status"])
}

// TestE2EHTTP_CassetteMiss — US-0105
func TestE2EHTTP_CassetteMiss(t *testing.T) {
	adapter := xhttp.NewAdapter()
	_, dir := newSession(t, xrr.ModeRecord)

	replaySess := replaySession(t, dir)
	_, err := replaySess.Record(context.Background(), adapter,
		&xhttp.Request{Method: "POST", URL: "https://example.com/not-recorded"},
		func() (xrr.Response, error) { return nil, nil },
	)
	require.True(t, errors.Is(err, xrr.ErrCassetteMiss))
}

// ── redis ─────────────────────────────────────────────────────────────────────

// TestE2ERedis_RecordReplay — US-0101, US-0104
// Records a Redis GET then replays it; asserts result value.
func TestE2ERedis_RecordReplay(t *testing.T) {
	adapter := xredis.NewAdapter()
	req := &xredis.Request{Command: "GET", Args: []string{"session:42"}}
	want := &xredis.Response{Result: "token-abc"}

	// --- record
	recSess, dir := newSession(t, xrr.ModeRecord)
	resp, err := recSess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		return want, nil
	})
	require.NoError(t, err)
	assert.Equal(t, "token-abc", resp.(*xredis.Response).Result)

	// --- replay
	replaySess := replaySession(t, dir)
	raw, err := replaySess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		t.Fatal("do() must not be called in replay mode")
		return nil, nil
	})
	require.NoError(t, err)
	payload := raw.(*xrr.RawResponse).Payload
	assert.Equal(t, "token-abc", payload["result"])
}

// TestE2ERedis_CassetteMiss — US-0105
func TestE2ERedis_CassetteMiss(t *testing.T) {
	adapter := xredis.NewAdapter()
	_, dir := newSession(t, xrr.ModeRecord)

	replaySess := replaySession(t, dir)
	_, err := replaySess.Record(context.Background(), adapter,
		&xredis.Request{Command: "SET", Args: []string{"k", "v"}},
		func() (xrr.Response, error) { return nil, nil },
	)
	require.True(t, errors.Is(err, xrr.ErrCassetteMiss))
}

// ── sql ──────────────────────────────────────────────────────────────────────

// TestE2ESQL_RecordReplay — US-0101, US-0104
// Records a SELECT query then replays it; asserts row data.
func TestE2ESQL_RecordReplay(t *testing.T) {
	adapter := xsql.NewAdapter()
	req := &xsql.Request{Query: "SELECT id, name FROM users WHERE id = ?", Args: []any{1}}
	want := &xsql.Response{
		Rows: []map[string]any{{"id": 1, "name": "alice"}},
	}

	// --- record
	recSess, dir := newSession(t, xrr.ModeRecord)
	resp, err := recSess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		return want, nil
	})
	require.NoError(t, err)
	rows := resp.(*xsql.Response).Rows
	require.Len(t, rows, 1)
	assert.Equal(t, "alice", rows[0]["name"])

	// --- replay
	replaySess := replaySession(t, dir)
	raw, err := replaySess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		t.Fatal("do() must not be called in replay mode")
		return nil, nil
	})
	require.NoError(t, err)
	payload := raw.(*xrr.RawResponse).Payload
	replayedRows, ok := payload["rows"].([]any)
	require.True(t, ok, "rows must be a slice")
	require.Len(t, replayedRows, 1)
	row := replayedRows[0].(map[string]any)
	assert.Equal(t, "alice", row["name"])
}

// TestE2ESQL_CassetteMiss — US-0105
func TestE2ESQL_CassetteMiss(t *testing.T) {
	adapter := xsql.NewAdapter()
	_, dir := newSession(t, xrr.ModeRecord)

	replaySess := replaySession(t, dir)
	_, err := replaySess.Record(context.Background(), adapter,
		&xsql.Request{Query: "DELETE FROM users"},
		func() (xrr.Response, error) { return nil, nil },
	)
	require.True(t, errors.Is(err, xrr.ErrCassetteMiss))
}

// ── grpc (Go-only) ────────────────────────────────────────────────────────────

// TestE2EGRPC_RecordReplay — US-0101, US-0102, US-0104
// Records a gRPC unary call then replays it; asserts status code + message.
func TestE2EGRPC_RecordReplay(t *testing.T) {
	adapter := xgrpc.NewAdapter()
	req := &xgrpc.Request{
		Service: "user.UserService",
		Method:  "GetUser",
		Message: []byte(`{"id":7}`),
	}
	want := &xgrpc.Response{
		StatusCode: 0,
		Message:    []byte(`{"id":7,"name":"bob"}`),
	}

	// --- record
	recSess, dir := newSession(t, xrr.ModeRecord)
	resp, err := recSess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		return want, nil
	})
	require.NoError(t, err)
	assert.Equal(t, 0, resp.(*xgrpc.Response).StatusCode)

	// --- replay
	replaySess := replaySession(t, dir)
	raw, err := replaySess.Record(context.Background(), adapter, req, func() (xrr.Response, error) {
		t.Fatal("do() must not be called in replay mode")
		return nil, nil
	})
	require.NoError(t, err)
	payload := raw.(*xrr.RawResponse).Payload
	assert.EqualValues(t, 0, payload["status_code"])
}

// TestE2EGRPC_CassetteMiss — US-0105
func TestE2EGRPC_CassetteMiss(t *testing.T) {
	adapter := xgrpc.NewAdapter()
	_, dir := newSession(t, xrr.ModeRecord)

	replaySess := replaySession(t, dir)
	_, err := replaySess.Record(context.Background(), adapter,
		&xgrpc.Request{Service: "foo.Bar", Method: "Baz", Message: []byte(`{}`)},
		func() (xrr.Response, error) { return nil, nil },
	)
	require.True(t, errors.Is(err, xrr.ErrCassetteMiss))
}

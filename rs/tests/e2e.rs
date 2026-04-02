// E2E adapter tests: exec, http, redis, sql.
// Each test: Record → Replay → assert match; plus CassetteMiss on unknown request.
//
// Stories: US-0101, US-0102, US-0104, US-0105

use std::collections::HashMap;

use tempfile::TempDir;
use xrr::{
    FileCassette, Mode, Session, XrrError,
    adapters::{
        exec::{ExecAdapter, ExecRequest, ExecResponse},
        http::{HttpAdapter, HttpRequest, HttpResponse},
        redis::{RedisAdapter, RedisRequest, RedisResponse},
        sql::{SqlAdapter, SqlRequest, SqlResponse},
    },
};

// ── helpers ──────────────────────────────────────────────────────────────────

fn session(dir: &std::path::Path, mode: Mode) -> Session {
    Session::new(mode, FileCassette::new(dir))
}

// ── exec ─────────────────────────────────────────────────────────────────────

// US-0101, US-0104
#[test]
fn exec_record_replay() {
    let tmp = TempDir::new().unwrap();

    let req = ExecRequest {
        argv: vec!["echo".into(), "xrr".into()],
        stdin: "".into(),
        env: HashMap::new(),
    };
    let recorded_resp = ExecResponse {
        stdout: "xrr\n".into(),
        stderr: "".into(),
        exit_code: 0,
        duration_ms: 5,
    };

    // Record
    let rec_resp = session(tmp.path(), Mode::Record)
        .record(&ExecAdapter, &req, || Ok(recorded_resp.clone()))
        .unwrap();
    assert_eq!(rec_resp.stdout, "xrr\n");
    assert_eq!(rec_resp.exit_code, 0);

    // Replay — do_ must NOT be called; response must match recorded value
    let mut do_called = false;
    let rep_resp = session(tmp.path(), Mode::Replay)
        .record(&ExecAdapter, &req, || {
            do_called = true;
            Ok(ExecResponse {
                stdout: "WRONG".into(),
                stderr: "".into(),
                exit_code: 1,
                duration_ms: 0,
            })
        })
        .unwrap();

    assert!(!do_called, "do_ must not run during replay");
    assert_eq!(rep_resp.stdout, "xrr\n");
    assert_eq!(rep_resp.exit_code, 0);
}

// US-0105
#[test]
fn exec_replay_miss_returns_cassette_miss() {
    let tmp = TempDir::new().unwrap();

    let req = ExecRequest {
        argv: vec!["unknown-command".into()],
        stdin: "".into(),
        env: HashMap::new(),
    };

    let result = session(tmp.path(), Mode::Replay).record(
        &ExecAdapter,
        &req,
        || Ok(ExecResponse { stdout: "".into(), stderr: "".into(), exit_code: 0, duration_ms: 0 }),
    );

    assert!(
        matches!(result, Err(XrrError::CassetteMiss { .. })),
        "expected CassetteMiss, got {:?}",
        result
    );
}

// ── http ─────────────────────────────────────────────────────────────────────

// US-0101, US-0104
#[test]
fn http_record_replay() {
    let tmp = TempDir::new().unwrap();

    let req = HttpRequest {
        method: "GET".into(),
        url: "https://api.example.com/v1/ping".into(),
        headers: HashMap::new(),
        body: vec![],
    };
    let recorded_resp = HttpResponse {
        status: 200,
        headers: HashMap::new(),
        body: b"pong".to_vec(),
    };

    // Record
    let rec_resp = session(tmp.path(), Mode::Record)
        .record(&HttpAdapter, &req, || Ok(recorded_resp.clone()))
        .unwrap();
    assert_eq!(rec_resp.status, 200);
    assert_eq!(rec_resp.body, b"pong");

    // Replay
    let mut do_called = false;
    let rep_resp = session(tmp.path(), Mode::Replay)
        .record(&HttpAdapter, &req, || {
            do_called = true;
            Ok(HttpResponse { status: 500, headers: HashMap::new(), body: vec![] })
        })
        .unwrap();

    assert!(!do_called, "do_ must not run during replay");
    assert_eq!(rep_resp.status, 200);
    assert_eq!(rep_resp.body, b"pong");
}

// US-0105
#[test]
fn http_replay_miss_returns_cassette_miss() {
    let tmp = TempDir::new().unwrap();

    let req = HttpRequest {
        method: "POST".into(),
        url: "https://api.example.com/v1/never-recorded".into(),
        headers: HashMap::new(),
        body: b"payload".to_vec(),
    };

    let result = session(tmp.path(), Mode::Replay).record(
        &HttpAdapter,
        &req,
        || Ok(HttpResponse { status: 200, headers: HashMap::new(), body: vec![] }),
    );

    assert!(
        matches!(result, Err(XrrError::CassetteMiss { .. })),
        "expected CassetteMiss, got {:?}",
        result
    );
}

// ── redis ────────────────────────────────────────────────────────────────────

// US-0101, US-0104
#[test]
fn redis_record_replay() {
    let tmp = TempDir::new().unwrap();

    let req = RedisRequest {
        command: "GET".into(),
        args: vec!["session:42".into()],
    };
    let recorded_resp = RedisResponse {
        result: serde_json::json!("token-abc"),
    };

    // Record
    let rec_resp = session(tmp.path(), Mode::Record)
        .record(&RedisAdapter, &req, || Ok(recorded_resp.clone()))
        .unwrap();
    assert_eq!(rec_resp.result, serde_json::json!("token-abc"));

    // Replay
    let mut do_called = false;
    let rep_resp = session(tmp.path(), Mode::Replay)
        .record(&RedisAdapter, &req, || {
            do_called = true;
            Ok(RedisResponse { result: serde_json::json!(null) })
        })
        .unwrap();

    assert!(!do_called, "do_ must not run during replay");
    assert_eq!(rep_resp.result, serde_json::json!("token-abc"));
}

// US-0105
#[test]
fn redis_replay_miss_returns_cassette_miss() {
    let tmp = TempDir::new().unwrap();

    let req = RedisRequest {
        command: "SET".into(),
        args: vec!["nope".into(), "val".into()],
    };

    let result = session(tmp.path(), Mode::Replay).record(
        &RedisAdapter,
        &req,
        || Ok(RedisResponse { result: serde_json::json!(null) }),
    );

    assert!(
        matches!(result, Err(XrrError::CassetteMiss { .. })),
        "expected CassetteMiss, got {:?}",
        result
    );
}

// ── sql ──────────────────────────────────────────────────────────────────────

// US-0101, US-0104
#[test]
fn sql_record_replay() {
    let tmp = TempDir::new().unwrap();

    let req = SqlRequest {
        query: "SELECT id, name FROM users WHERE id = $1".into(),
        args: vec![serde_json::json!(7)],
    };
    let recorded_resp = SqlResponse {
        rows: vec![serde_json::json!({"id": 7, "name": "alice"})],
        rows_affected: 1,
    };

    // Record
    let rec_resp = session(tmp.path(), Mode::Record)
        .record(&SqlAdapter, &req, || Ok(recorded_resp.clone()))
        .unwrap();
    assert_eq!(rec_resp.rows_affected, 1);
    assert_eq!(rec_resp.rows[0]["name"], "alice");

    // Replay
    let mut do_called = false;
    let rep_resp = session(tmp.path(), Mode::Replay)
        .record(&SqlAdapter, &req, || {
            do_called = true;
            Ok(SqlResponse { rows: vec![], rows_affected: 0 })
        })
        .unwrap();

    assert!(!do_called, "do_ must not run during replay");
    assert_eq!(rep_resp.rows_affected, 1);
    assert_eq!(rep_resp.rows[0]["name"], "alice");
}

// US-0105
#[test]
fn sql_replay_miss_returns_cassette_miss() {
    let tmp = TempDir::new().unwrap();

    let req = SqlRequest {
        query: "DELETE FROM ghosts".into(),
        args: vec![],
    };

    let result = session(tmp.path(), Mode::Replay).record(
        &SqlAdapter,
        &req,
        || Ok(SqlResponse { rows: vec![], rows_affected: 0 }),
    );

    assert!(
        matches!(result, Err(XrrError::CassetteMiss { .. })),
        "expected CassetteMiss, got {:?}",
        result
    );
}

use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};

use crate::{error::XrrError, Adapter};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct SqlRequest {
    pub query: String,
    pub args: Vec<serde_json::Value>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct SqlResponse {
    pub rows: Vec<serde_json::Value>,
    pub rows_affected: i64,
}

pub struct SqlAdapter;

fn normalize_query(q: &str) -> String {
    // to_lowercase + collapse whitespace
    q.to_lowercase()
        .split_whitespace()
        .collect::<Vec<_>>()
        .join(" ")
}

impl Adapter for SqlAdapter {
    type Req = SqlRequest;
    type Resp = SqlResponse;

    fn id(&self) -> &str {
        "sql"
    }

    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError> {
        let normalized = normalize_query(&req.query);
        let canonical = serde_json::to_string(&serde_json::json!({
            "args": req.args,
            "query": normalized,
        }))?;
        let hash = Sha256::digest(canonical.as_bytes());
        Ok(hex::encode(&hash[..4]))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn req(query: &str, args: &[serde_json::Value]) -> SqlRequest {
        SqlRequest {
            query: query.into(),
            args: args.to_vec(),
        }
    }

    #[test]
    fn fingerprint_deterministic() {
        let a = SqlAdapter;
        let r = req("SELECT * FROM users WHERE id = $1", &[serde_json::json!(42)]);
        let fp1 = a.fingerprint(&r).unwrap();
        let fp2 = a.fingerprint(&r).unwrap();
        assert_eq!(fp1, fp2);
        assert_eq!(fp1.len(), 8);
    }

    #[test]
    fn case_and_whitespace_normalized() {
        let a = SqlAdapter;
        let fp1 = a.fingerprint(&req("SELECT   *  FROM users", &[])).unwrap();
        let fp2 = a.fingerprint(&req("select * from users", &[])).unwrap();
        assert_eq!(fp1, fp2);
    }

    #[test]
    fn different_queries_different_fps() {
        let a = SqlAdapter;
        let fp1 = a.fingerprint(&req("select * from users", &[])).unwrap();
        let fp2 = a.fingerprint(&req("select * from orders", &[])).unwrap();
        assert_ne!(fp1, fp2);
    }
}

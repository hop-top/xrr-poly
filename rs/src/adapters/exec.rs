use std::collections::HashMap;

use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};

use crate::{error::XrrError, Adapter};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct ExecRequest {
    pub argv: Vec<String>,
    pub stdin: String,
    pub env: HashMap<String, String>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct ExecResponse {
    pub stdout: String,
    pub stderr: String,
    pub exit_code: i32,
    pub duration_ms: i64,
}

pub struct ExecAdapter;

impl Adapter for ExecAdapter {
    type Req = ExecRequest;
    type Resp = ExecResponse;

    fn id(&self) -> &str {
        "exec"
    }

    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError> {
        // canonical = sorted-key JSON of {argv, stdin}
        let canonical = serde_json::to_string(&serde_json::json!({
            "argv": req.argv,
            "stdin": req.stdin,
        }))?;
        let hash = Sha256::digest(canonical.as_bytes());
        Ok(hex::encode(&hash[..4]))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn req(argv: &[&str], stdin: &str) -> ExecRequest {
        ExecRequest {
            argv: argv.iter().map(|s| s.to_string()).collect(),
            stdin: stdin.into(),
            env: HashMap::new(),
        }
    }

    #[test]
    fn fingerprint_deterministic() {
        let a = ExecAdapter;
        let r = req(&["gh", "pr", "view", "123"], "");
        let fp1 = a.fingerprint(&r).unwrap();
        let fp2 = a.fingerprint(&r).unwrap();
        assert_eq!(fp1, fp2);
        assert_eq!(fp1.len(), 8);
    }

    #[test]
    fn different_inputs_different_fingerprints() {
        let a = ExecAdapter;
        let fp1 = a.fingerprint(&req(&["echo", "hello"], "")).unwrap();
        let fp2 = a.fingerprint(&req(&["echo", "world"], "")).unwrap();
        assert_ne!(fp1, fp2);
    }

}

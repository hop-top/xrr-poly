use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};

use crate::{error::XrrError, Adapter};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct RedisRequest {
    pub command: String,
    pub args: Vec<String>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct RedisResponse {
    pub result: serde_json::Value,
}

pub struct RedisAdapter;

impl Adapter for RedisAdapter {
    type Req = RedisRequest;
    type Resp = RedisResponse;

    fn id(&self) -> &str {
        "redis"
    }

    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError> {
        let canonical_str = format!(
            "{} {}",
            req.command.to_uppercase(),
            req.args.join(" ")
        );
        let canonical = serde_json::to_string(&serde_json::json!({
            "canonical": canonical_str,
        }))?;
        let hash = Sha256::digest(canonical.as_bytes());
        Ok(hex::encode(&hash[..4]))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn req(cmd: &str, args: &[&str]) -> RedisRequest {
        RedisRequest {
            command: cmd.into(),
            args: args.iter().map(|s| s.to_string()).collect(),
        }
    }

    #[test]
    fn fingerprint_deterministic() {
        let a = RedisAdapter;
        let r = req("GET", &["mykey"]);
        let fp1 = a.fingerprint(&r).unwrap();
        let fp2 = a.fingerprint(&r).unwrap();
        assert_eq!(fp1, fp2);
        assert_eq!(fp1.len(), 8);
    }

    #[test]
    fn case_insensitive_command() {
        let a = RedisAdapter;
        let fp1 = a.fingerprint(&req("get", &["key"])).unwrap();
        let fp2 = a.fingerprint(&req("GET", &["key"])).unwrap();
        assert_eq!(fp1, fp2);
    }

    #[test]
    fn different_keys_different_fps() {
        let a = RedisAdapter;
        let fp1 = a.fingerprint(&req("GET", &["key1"])).unwrap();
        let fp2 = a.fingerprint(&req("GET", &["key2"])).unwrap();
        assert_ne!(fp1, fp2);
    }
}

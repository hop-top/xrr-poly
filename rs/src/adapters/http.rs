use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};

use crate::{error::XrrError, Adapter};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct HttpRequest {
    pub method: String,
    pub url: String,
    pub headers: std::collections::HashMap<String, String>,
    pub body: Vec<u8>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct HttpResponse {
    pub status: u16,
    pub headers: std::collections::HashMap<String, String>,
    pub body: Vec<u8>,
}

pub struct HttpAdapter;

impl Adapter for HttpAdapter {
    type Req = HttpRequest;
    type Resp = HttpResponse;

    fn id(&self) -> &str {
        "http"
    }

    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError> {
        // path+query = everything after the host (no scheme/host)
        let path_query = extract_path_query(&req.url);
        let body_hash = hex::encode(&Sha256::digest(&req.body)[..4]);

        let canonical = serde_json::to_string(&serde_json::json!({
            "body_hash": body_hash,
            "method": req.method.to_uppercase(),
            "path_query": path_query,
        }))?;
        let hash = Sha256::digest(canonical.as_bytes());
        Ok(hex::encode(&hash[..4]))
    }
}

fn extract_path_query(url: &str) -> String {
    // Strip scheme and host; keep path + query.
    if let Some(rest) = url.strip_prefix("http://").or_else(|| url.strip_prefix("https://")) {
        if let Some(slash) = rest.find('/') {
            return rest[slash..].to_string();
        }
        return "/".to_string();
    }
    url.to_string()
}

#[cfg(test)]
mod tests {
    use super::*;

    fn req(method: &str, url: &str, body: &[u8]) -> HttpRequest {
        HttpRequest {
            method: method.into(),
            url: url.into(),
            headers: Default::default(),
            body: body.to_vec(),
        }
    }

    #[test]
    fn fingerprint_deterministic() {
        let a = HttpAdapter;
        let r = req("GET", "https://api.example.com/v1/repos", &[]);
        let fp1 = a.fingerprint(&r).unwrap();
        let fp2 = a.fingerprint(&r).unwrap();
        assert_eq!(fp1, fp2);
        assert_eq!(fp1.len(), 8);
    }

    #[test]
    fn different_paths_different_fps() {
        let a = HttpAdapter;
        let fp1 = a.fingerprint(&req("GET", "https://example.com/foo", &[])).unwrap();
        let fp2 = a.fingerprint(&req("GET", "https://example.com/bar", &[])).unwrap();
        assert_ne!(fp1, fp2);
    }

    #[test]
    fn host_ignored() {
        let a = HttpAdapter;
        let fp1 = a.fingerprint(&req("GET", "https://host-a.com/path", &[])).unwrap();
        let fp2 = a.fingerprint(&req("GET", "https://host-b.com/path", &[])).unwrap();
        assert_eq!(fp1, fp2);
    }
}

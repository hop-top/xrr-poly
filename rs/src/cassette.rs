use std::path::PathBuf;

use chrono::Utc;
use serde::{de::DeserializeOwned, Deserialize, Serialize};

use crate::error::XrrError;

#[derive(Serialize, Deserialize)]
struct Envelope<T> {
    xrr: String,
    adapter: String,
    fingerprint: String,
    recorded_at: String,
    payload: T,
}

pub struct FileCassette {
    dir: PathBuf,
}

impl FileCassette {
    pub fn new(dir: impl Into<PathBuf>) -> Self {
        Self { dir: dir.into() }
    }

    pub fn save<Req: Serialize, Resp: Serialize>(
        &self,
        adapter_id: &str,
        fingerprint: &str,
        req: &Req,
        resp: &Resp,
    ) -> Result<(), XrrError> {
        let now = Utc::now().format("%Y-%m-%dT%H:%M:%SZ").to_string();
        self.write(adapter_id, fingerprint, "req", &now, req)?;
        self.write(adapter_id, fingerprint, "resp", &now, resp)?;
        Ok(())
    }

    fn write<T: Serialize>(
        &self,
        adapter_id: &str,
        fingerprint: &str,
        kind: &str,
        recorded_at: &str,
        payload: &T,
    ) -> Result<(), XrrError> {
        let env = Envelope {
            xrr: "1".into(),
            adapter: adapter_id.into(),
            fingerprint: fingerprint.into(),
            recorded_at: recorded_at.into(),
            payload,
        };
        let data = serde_yaml::to_string(&env)?;
        let path = self
            .dir
            .join(format!("{}-{}.{}.yaml", adapter_id, fingerprint, kind));
        std::fs::write(path, data)?;
        Ok(())
    }

    pub fn load<Req: DeserializeOwned, Resp: DeserializeOwned>(
        &self,
        adapter_id: &str,
        fingerprint: &str,
    ) -> Result<(Req, Resp), XrrError> {
        let req = self.read::<Req>(adapter_id, fingerprint, "req")?;
        let resp = self.read::<Resp>(adapter_id, fingerprint, "resp")?;
        Ok((req, resp))
    }

    fn read<T: DeserializeOwned>(
        &self,
        adapter_id: &str,
        fingerprint: &str,
        kind: &str,
    ) -> Result<T, XrrError> {
        let path = self
            .dir
            .join(format!("{}-{}.{}.yaml", adapter_id, fingerprint, kind));
        let data = std::fs::read_to_string(&path).map_err(|e| {
            if e.kind() == std::io::ErrorKind::NotFound {
                XrrError::CassetteMiss {
                    adapter: adapter_id.into(),
                    fingerprint: fingerprint.into(),
                }
            } else {
                XrrError::Io(e)
            }
        })?;

        // Deserialize into raw value map, then extract payload.
        let raw: serde_yaml::Value = serde_yaml::from_str(&data)?;
        let payload = raw
            .get("payload")
            .ok_or_else(|| {
                XrrError::Io(std::io::Error::new(
                    std::io::ErrorKind::InvalidData,
                    format!("missing payload in {}", kind),
                ))
            })?
            .clone();
        let result: T = serde_yaml::from_value(payload)?;
        Ok(result)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::adapters::exec::{ExecRequest, ExecResponse};
    use std::collections::HashMap;
    use tempfile::TempDir;

    fn make_req() -> ExecRequest {
        ExecRequest {
            argv: vec!["gh".into(), "pr".into(), "view".into()],
            stdin: "".into(),
            env: HashMap::new(),
        }
    }

    fn make_resp() -> ExecResponse {
        ExecResponse {
            stdout: "ok\n".into(),
            stderr: "".into(),
            exit_code: 0,
            duration_ms: 10,
        }
    }

    #[test]
    fn roundtrip() {
        let tmp = TempDir::new().unwrap();
        let cassette = FileCassette::new(tmp.path());
        let req = make_req();
        let resp = make_resp();

        cassette.save("exec", "abcd1234", &req, &resp).unwrap();
        let (loaded_req, loaded_resp): (ExecRequest, ExecResponse) =
            cassette.load("exec", "abcd1234").unwrap();

        assert_eq!(loaded_req.argv, req.argv);
        assert_eq!(loaded_resp.stdout, resp.stdout);
        assert_eq!(loaded_resp.exit_code, 0);
    }

    #[test]
    fn miss_returns_cassette_miss_error() {
        let tmp = TempDir::new().unwrap();
        let cassette = FileCassette::new(tmp.path());
        let result: Result<(ExecRequest, ExecResponse), _> =
            cassette.load("exec", "deadbeef");
        assert!(matches!(result, Err(XrrError::CassetteMiss { .. })));
    }
}

pub mod adapters;
pub mod cassette;
pub mod error;
pub mod session;

pub use cassette::FileCassette;
pub use error::XrrError;
pub use session::{Mode, Session};

use serde::{de::DeserializeOwned, Serialize};

/// Adapter intercepts one channel type.
pub trait Adapter: Send + Sync {
    type Req: Serialize + DeserializeOwned + Send;
    type Resp: Serialize + DeserializeOwned + Send;

    fn id(&self) -> &str;
    fn fingerprint(&self, req: &Self::Req) -> Result<String, XrrError>;
}

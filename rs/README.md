# xrr — Rust SDK

> Auto-published from [xrr-poly](https://github.com/hop-top/xrr-poly).
> Do not open issues or PRs here — contribute to xrr-poly instead.

## Install

```bash
cargo add xrr
```

## Usage

```rust
let mut sess = Session::new(cassette("fixtures/my-test"));
let resp = sess.record("http-get-users", &adapter)?;
sess.close();
```

## License

MIT — see [LICENSE](LICENSE)

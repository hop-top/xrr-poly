# xrr — TypeScript SDK

> Auto-published from [xrr-poly](https://github.com/hop-top/xrr-poly).
> Do not open issues or PRs here — contribute to xrr-poly instead.

## Install

```bash
npm install @hop-top/xrr
```

## Usage

```ts
const sess = new Session({ cassette: "fixtures/my-test" });
const resp = await sess.record("http-get-users", adapter);
sess.close();
```

## License

MIT — see [LICENSE](LICENSE)

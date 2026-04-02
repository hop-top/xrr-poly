# xrr — PHP SDK

> Auto-published from [xrr-poly](https://github.com/hop-top/xrr-poly).
> Do not open issues or PRs here — contribute to xrr-poly instead.

## Install

```bash
composer require hop-top/xrr
```

## Usage

```php
$sess = new Session(cassette: 'fixtures/my-test');
$resp = $sess->record('http-get-users', $adapter);
$sess->close();
```

## License

MIT — see [LICENSE](LICENSE)

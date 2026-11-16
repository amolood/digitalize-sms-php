# Changelog

## 1.0.0 — 2026-11-16

- First release, for SMS API v1.
- Send from templates (`send`, `sendOtp`), read messages (`get`, `list`, `all`), templates and account balance.
- Idempotency key on every send; automatic retries on timeouts, 429 and 5xx without duplicate sends.
- Typed exceptions per error.
- Laravel: auto-discovered provider, config, `DigitalizeSms` facade, `digitalize-sms` notification channel.

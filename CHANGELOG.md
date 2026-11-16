# Changelog

## 1.0.1 — 2026-11-16

- Idempotency keys and API keys are checked before sending; header values with line breaks are refused.
- The headers array (which holds the API key) is marked sensitive, so the key stays out of stack traces.

## 1.0.0 — 2026-11-16

- First release, for SMS API v1.
- Send from templates (`send`, `sendOtp`), read messages (`get`, `list`, `all`), templates and account balance.
- Idempotency key on every send; automatic retries on timeouts, 429 and 5xx without duplicate sends.
- Typed exceptions per error.
- Laravel: auto-discovered provider, config, `DigitalizeSms` facade, `digitalize-sms` notification channel.

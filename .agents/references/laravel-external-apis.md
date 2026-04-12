# Laravel third-party API integrations

Use this when adding or refactoring HTTP clients for external APIs (Stripe, SaaS, webhooks targets, etc.). Pattern adapted from a connector/resource style (similar in spirit to Saloon’s mental model).

## Placement and naming

- Put external API code under `app/Http/Integrations/{Vendor}` (or per-product folder), not under a generic `app/Services` tree, so internal domain services stay separate from outbound HTTP.
- The **connector** is the class that owns how you attach to one API base URL, default headers, timeout, JSON mode, etc.
- **Resources** are grouped by API area (e.g. `DatabaseResource`, `BackupResource`). Each resource receives the connector (constructor injection) and implements methods for that slice of the API.

## Configuration

- Store base URL, keys, and service-specific IDs in `config/services.php` with values from `env()`.
- Read settings via `config('services.{key}.…')` inside integrations — do not call `env()` outside config.

## Container registration

- Prefer **self-contained bindings**: a static `register(Application $app): void` on the connector that calls `$app->bind(Connector::class, fn () => new Connector(...))` with a fully configured `Http::` pending request.
- The app service provider only calls `PlanetscaleConnector::register($app)` (or equivalent) — bindings stay next to the class that uses them.

## HTTP client behavior

- Build the `PendingRequest` with `Http::baseUrl(...)`, `timeout(...)`, `withHeaders(...)` or `withToken(...)` as the API requires, plus `asJson()` / `acceptJson()` when the API is JSON.
- Expose a single **`send` method** on the connector (or a thin trait) that delegates to the pending request and uses `->throw()` so failed responses raise consistently. Map HTTP verbs with a small enum or constants if you use them everywhere.
- For non-trivial auth header shapes, set headers explicitly rather than forcing `withToken()` if it does not match the spec.

## Resources and request bodies

- Resource methods call `$this->connector->send($method, $uri, $options)`.
- Use **entities / DTOs** (or dedicated request objects) when a call needs several path segments plus a body — e.g. `toRequestBody()` for POST payload and typed properties for URL pieces.
- Return **domain-friendly types** from resources: `Collection`, arrays, or DTOs — optionally map raw JSON in one place rather than leaking arrays everywhere.

## Pagination, retries, and packages

- For heavy pagination, backoff, or many connectors, **prefer Saloon** (or a similar dedicated HTTP integration layer) instead of hand-rolling every edge case.
- Keep try/catch **meaningful**: avoid catch-rethrow-only blocks; either handle, wrap, or let the exception propagate without noise.

## Testing

- Fake the HTTP layer (`Http::fake`, mocking the connector, or contract tests) so resources are tested without live APIs; same ideas as testing JSON APIs with Pest apply to integration classes.

## Code quality

- Prefer `final readonly` connector and resource classes where you do not intend extension.
- Keep connectors thin: wiring, `send`, and factory methods like `databases()` / `backups()` that return resources — not large procedural scripts.

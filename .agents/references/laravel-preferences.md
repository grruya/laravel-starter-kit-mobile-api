## Controllers

- A controller should only hold CRUD actions: `index`, `store`, `show`, `update`, `destroy`.
- Use a single-purpose invocable controller for anything else (e.g., `UserExportController`).
- Do not add private methods, business logic, or validation in controllers; delegate to Action classes and FormRequests.

## Action Classes

- Make each action `final readonly` with a single public `handle()` and keep other methods `private`.
- Start the class name with a verb (e.g., `CreateOrder`, `ProcessPayment`).
- Keep actions free of HTTP and session so any entry point can call them.
- Wrap multi-step database changes in `DB::transaction`, but expect pain on very large tables (e.g., history).

## Migrations

- Avoid `onDelete('cascade')` in migrations and perform deletes in application code for clearer side effects.
- Avoid database defaults in migrations; set defaults inside Actions.

## Models

- Document every column with `@property` in PHPDoc for full static analysis coverage.

## Complex queries

- Place complex queries in `app/Queries` (e.g., `UserListQuery`) and return an Eloquent Builder the controller can chain.

## Form Requests and Validation

- Use the `after` callback in Form Requests for validation checks that rely on the database or other post-basic-rule logic
- Apply sensible min and max rules on strings (e.g., `min:3` for a name).
- Prefer `#[CurrentUser] User $user` injection over `$request->user()`.

## Jobs

- Keep the job `$tries` value low; one is enough in most cases.
- Pass serializable arguments such as a user id instead of heavy objects.
- Use the `DeleteWhenMissingModels` trait when the job references a model that might be deleted.

## File System and Atomic Operations

- If file operations must track a database transaction, commit the database work first and queue the file work.
- Call `deleteFileAfterSend()` on temporary download files so they are removed after the response finishes.

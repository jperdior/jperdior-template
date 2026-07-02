# Keep the thin CommandHandler/QueryHandler → UseCase split

Every Command and Query has a thin Handler whose only job is to delegate to a UseCase class. Handlers are the bus-facing adapters: they carry the `CommandHandler`/`QueryHandler` marker interfaces and the Messenger `_instanceof` wiring, and nothing else. UseCases hold the application behaviour and stay free of bus concerns, so they can be invoked directly (from another use case, a console command, a future non-bus entry point) without a bus round-trip.

Flattening the two — naming UseCases `*Handler` and wiring them to the bus directly — was considered and rejected, for three reasons:

1. **UseCase reuse.** UseCases can be composed and called from any entry point; tying them to the bus marker interface would couple every reuse to Messenger.
2. **Cross-cutting concerns live in the Handler.** Logging, metrics, or tracing can be added per-handler without touching the UseCase.
3. **Separation of concerns.** Bus plumbing and application behaviour never share a class.

The scaffold skills (`/add-command`, `/add-query`) generate both halves.

## Consequences

- Architecture reviews must not flag Handler → UseCase delegation as pass-through friction; the "extra hop" is the accepted cost.
- New contexts follow the same two-class shape for every command and query.

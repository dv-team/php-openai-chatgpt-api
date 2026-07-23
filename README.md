# OpenAI ChatGPT API Client for PHP

Lightweight PHP client for OpenAI's Responses API.

## Features

- Low-level `ChatGPT` client for single Responses API requests
- Stateful `GPTConversation` with context management
- Bounded multi-round tool execution
- PHP callable and manually defined function tools
- Hosted web search
- JSON Schema responses with local validation
- Serializable and resumable conversation sessions
- Prompt-cache configuration and usage metrics
- Image and custom attachment inputs
- Text-to-speech output
- Pluggable PSR-18 HTTP transport

## Requirements and installation

The library requires PHP 8.1 or newer and the extensions listed in
`composer.json`.

Install the package with `composer require dv-team/openai-chatgpt-api`.

Applications must provide an HTTP transport. The bundled `Psr18HttpClient`
adapter accepts a PSR-18 client and PSR-17 request and stream factories.

## Developer documentation

- [Getting started and low-level requests](docs/getting-started.md)
- [Conversations, serialization, restoration, and prompt caching](docs/conversations.md)
- [Attachments and custom attachment serialization](docs/attachments.md)
- [Structured JSON responses](docs/structured-responses.md)
- [Tool calling](docs/tool-calling.md)
- [Web search](docs/web-search.md)
- [Text to speech](docs/text-to-speech.md)
- [Models and reasoning](docs/models-and-reasoning.md)
- [Executable examples](examples/README.md)

All detailed code examples live in `docs/` or `examples/`. The README remains a
compact project and documentation index.

## API overview

### `ChatGPT`

`DvTeam\ChatGPT\ChatGPT` is the stateless, low-level client. The caller supplies
the complete context for one API request and receives a `ChatResponse`.
`ChatGPT` does not retain context or execute requested tools.

### `GPTConversation`

`DvTeam\ChatGPT\GPTConversation` owns a conversation context, appends model
responses, executes registered PHP tools, and records their results.

`step()` performs one API request. `runUntilResponse()` continues through tool
rounds until a visible response is produced or its configured step limit is
reached.

Complete sessions can be persisted with `toArray()` or `toJson()` and restored
with `fromArray()` or `fromJson()`. Runtime dependencies and the currently
available tools are injected during restoration.

## Running the executable examples

Set `OPENAI_API_KEY`, install development dependencies with `composer install`,
and follow the commands in [the examples index](examples/README.md).

## Development

Run the unit tests with `composer test`.

Run static analysis with `composer run phpstan`.

## Notes

- The library uses the Responses API input format.
- JSON Schema responses are validated with `opis/json-schema`.
- A custom HTTP adapter must implement `HttpPostInterface` and return an
  `HttpResponse`.
- `ChatResponse::firstChoice()` returns the first response choice.

## Maintenance

Whenever new functionality is added, update both `README.md` and `AGENTS.md` so
human-facing and agent-facing documentation stay aligned.

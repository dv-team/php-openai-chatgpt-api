# Conversations, Persistence, and Prompt Caching

This document describes the project's stateful API. It is written for both
human developers and AI agents working with this repository.

The most important distinction is:

| Responsibility | Component |
| --- | --- |
| Send one request to the Responses API | `ChatGPT` |
| Maintain context, absorb responses, and execute tools | `GPTConversation` |
| Persist a session in the application | `toArray()` / `toJson()` |
| Temporarily reuse an OpenAI prompt prefix | Prompt caching |

A serialized session and the OpenAI prompt cache are independent mechanisms.
The session belongs to the application. OpenAI manages the prompt cache, and
its contents cannot be exported with the session.

## How conversations work

`ChatGPT::chat()` is the low-level entry point. The caller provides the complete
context and receives exactly one API response. The method does not retain
conversation state or execute tools.

`GPTConversation` adds a stateful workflow. An instance contains:

- the ordered context of `ChatInput`, `ChatOutput`, and `ToolResult` objects;
- the PHP tools that are currently executable;
- the model and its reasoning configuration;
- an optional structured response format;
- token, sampling, and prompt-cache settings.

One call to `step()` performs the following operations:

1. Build schemas for the currently registered PHP tools.
2. Pass the complete context and current configuration to `ChatGPT::chat()`.
3. Append the model response to the context as a `ChatOutput`.
4. Execute any tools requested by the model locally.
5. Append each result to the context as a `ToolResult`.
6. Return the response from this one API request.

Use `step()` when the application must control tool rounds and persist after
each round. Use `runUntilResponse()` when the conversation should continue
automatically until the model produces a visible response:

```php
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMLargeReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

$conversation = new GPTConversation(
    chat: $chat,
    context: [
        new ChatInput('You are an assistant for used products.', role: 'developer'),
        new ChatInput('Start the interview for a Canon EOS 5D Mark IV.'),
    ],
    callableTools: [$webSearchTool, $submitProductTool],
    model: new LLMLargeReasoning(ReasoningEffort::Medium),
);

$answer = $conversation->runUntilResponse(maxSteps: 8);

echo $answer->textResult;
```

`runUntilResponse()` is intentionally bounded. It throws a `RuntimeException`
if no visible response is produced within `maxSteps`. `step(true)` is the
shorthand that uses the default limit of eight API rounds.

Do not send new user input directly to `ChatGPT::chat()` when using a
conversation. Add it to the active conversation first:

```php
$conversation->addMessage(new ChatInput('The battery is included.'));
$answer = $conversation->runUntilResponse(maxSteps: 8);
```

Tools can be added or completely replaced during a session:

```php
$conversation->addTool($additionalTool);
$conversation->setTools([$webSearchTool, $replacementTool]);
```

Registered tools apply only to future model responses. If the model requests a
tool for which no executable PHP callable is currently registered,
`GPTConversation` throws a `RuntimeException`.

### Context and raw output items

`ChatOutput` retains raw Responses API output items, including reasoning items,
IDs, and function-call metadata. Later requests replay these items unchanged.
This is required for lossless GPT-5.6 tool rounds and for resuming long-running
conversations.

The application must not reduce the context to visible text or remove reasoning
items and tool calls from historical `ChatOutput` objects.

## Serializing a complete conversation

Use `toArray()` and `toJson()` for new implementations:

```php
$sessionPayload = $conversation->toArray();
$sessionJson = $conversation->toJson();
```

The versioned session format contains:

- the complete context, including historical tool calls, tool results, and raw
  Responses API output items;
- the effective model name, model capabilities, and reasoning effort;
- the structured response schema and its `strict` setting;
- `maxTokens`, `temperature`, and `topP`;
- `promptCacheKey` and `PromptCacheOptions`.

The format intentionally excludes:

- the `ChatGPT` client, HTTP client, and API key;
- executable PHP callables and other runtime dependencies;
- data stored in the OpenAI prompt cache;
- any guarantee of reusable server-side OpenAI conversation state.

The JSON can be stored in a database text column, for example. It contains the
complete conversation and must be protected by the same access and retention
rules as other sensitive application data.

### Restoring an executable conversation from JSON

Inject infrastructure and the currently available tools when restoring a
session:

```php
$sessionJson = $sessionStorage->load($sessionId);

$conversation = GPTConversation::fromJson(
    chat: $chat,
    json: $sessionJson,
    tools: [$webSearchTool, $submitProductTool],
);

$conversation->addMessage(new ChatInput('The item has light scratches.'));
$answer = $conversation->runUntilResponse(maxSteps: 8);
```

Use the corresponding method when the data is already available as a PHP
array:

```php
$conversation = GPTConversation::fromArray(
    chat: $chat,
    payload: $sessionPayload,
    tools: [$webSearchTool, $submitProductTool],
);
```

The third argument contains only the tools that should be available from this
point onward. Historical tool calls remain in the context, but they do not need
to map to PHP tools that still exist. Restoration does not execute them again.

The following changes are therefore valid between processes:

- new tools become available;
- previous tools disappear;
- a new PHP implementation takes over an existing tool name.

If the model requests a new tool call after restoration, an executable callable
for that tool name must be present in the current tool set.

### Recommended persistence point

Persist the session at least after every visible user or assistant turn. For
tools with external side effects, such as placing an order or sending a
message, also use idempotent tool operations and application-level execution
IDs. Otherwise, a process failure between tool execution and session
persistence could cause the operation to be repeated.

When this level of control matters, execute individual `step()` rounds and
persist after each round instead of wrapping several tool rounds in one
`runUntilResponse()` call.

### Attachments

`ChatImageUrl` can be serialized without additional registration. A custom
`ChatInput` attachment must implement `ContextSerializable` in addition to
`ChatAttachment`. Register its serialized type before calling `fromArray()` or
`fromJson()`:

```php
ChatInput::registerAttachmentType(
    'document_reference',
    [DocumentReference::class, 'contextUnserialize'],
);
```

Restoration throws an `InvalidArgumentException` when no decoder is registered
for an attachment type.

### Context-only APIs

Two narrower alternatives remain available for older integrations:

- `GPTConversation::serialize()` and `GPTConversation::fromSerialized()`;
- `ChatGPT::contextAsArray()` and `ChatGPT::contextFromArray()`.

They primarily transport the context. The model, response schema, tools, and
other configuration must be supplied separately during restoration. Prefer
`toArray()` / `fromArray()` or `toJson()` / `fromJson()` for new, fully
resumable sessions.

The full session format includes a version number. Unknown versions are
rejected. When the format changes in the future, the application must migrate
stored payloads before passing them to `fromArray()`.

## How prompt caching works

Prompt caching is not a response cache. OpenAI can reuse a previously processed
input prefix, but it generates a new response for every request. Caching does
not semantically alter model output.

OpenAI automatically enables prompt caching for eligible prompts of at least
1,024 tokens. A cache hit is possible only for an exact prefix match. Put
static content such as instructions and examples at the beginning and dynamic
user or session data at the end. Messages, images, tool definitions, and
structured response schemas are part of the cache-relevant input.

For GPT-5.6, set a stable `promptCacheKey` so requests with a shared prefix are
routed to the matching cache more reliably:

```php
use DvTeam\ChatGPT\Common\PromptCacheOptions;

$conversation = new GPTConversation(
    chat: $chat,
    context: $initialContext,
    callableTools: $tools,
    model: $model,
    promptCacheKey: 'used-product-interview:v3:tenant-42',
    promptCacheOptions: new PromptCacheOptions(),
);
```

`new PromptCacheOptions()` is equivalent to:

```php
new PromptCacheOptions(
    mode: 'implicit',
    ttl: '30m',
);
```

`implicit` is the recommended default. OpenAI automatically places a cache
breakpoint on the latest message in this mode. For GPT-5.6, `30m` is currently
the only supported TTL value and specifies a minimum lifetime. A prefix may
remain in the cache for longer.

The options do not have to be supplied for implicit caching. A
`promptCacheKey` is not a cache ID and does not create an application-side
cache entry. It improves the routing of repeated requests with identical
prefixes.

### Explicit mode

`PromptCacheOptions` also accepts `mode: 'explicit'`. In this mode, OpenAI uses
only explicitly marked breakpoints. Prompt caching does not occur without a
`prompt_cache_breakpoint`.

The built-in `ChatInput` text messages currently have no dedicated breakpoint
API. Use `explicit` with this client only when a custom `ChatMessage` or
attachment type emits a Responses API content block with a valid
`prompt_cache_breakpoint`. For ordinary conversations, `implicit` remains the
safe choice.

### Resuming and reusing the cache

`toArray()` and `toJson()` store the cache key and cache options. After
`fromArray()` or `fromJson()`, the conversation sends these values with every
request again. This allows OpenAI to reuse a cached prefix that still exists.

A cache hit is not guaranteed:

- the prefix must still match exactly;
- the prompt must be long enough;
- the entry must not have been evicted;
- the model, images, tools, and response schema must match at the relevant
  prefix;
- changing the tool set can therefore prevent a hit despite using the same key.

Neither a response ID nor a cache handle must be stored for cache reuse.
Session persistence supplies the complete context; `promptCacheKey` only
assists routing to the OpenAI cache.

### Measuring cache usage

Token usage is available on both `ChatResponse` and `ChatResponseChoice`:

```php
$choice = $conversation->step();

printf(
    "Cache read: %d, cache written: %d\n",
    $choice->usage?->cachedTokens ?? 0,
    $choice->usage?->cacheWriteTokens ?? 0,
);
```

- `cachedTokens` is the number of input tokens read from the cache.
- `cacheWriteTokens` is the number of tokens written to the cache on GPT-5.6.
- A value of `0` can indicate a cache miss or an ineligible prompt below 1,024
  tokens.

On GPT-5.6, cache writes are billed at 1.25 times the uncached input-token rate.
Applications should monitor read and write volume together instead of assuming
that a configured key provides a cost benefit.

### What to do

- Put long, stable instructions and examples at the beginning.
- Put variable input at the end.
- Use a stable, versioned `promptCacheKey` for requests that share a prefix.
- Keep the model, tool schemas, image parameters, and response schema stable
  where possible.
- Log `cachedTokens` and `cacheWriteTokens`.
- When traffic exceeds approximately 15 requests per minute per key, partition
  it across more keys with a stable mapping.
- Version the key when the intended stable prefix changes.

### What not to do

- Do not generate a random cache key for every request.
- Do not expect the same key alone to force a cache hit.
- Do not assume that JSON serialization contains the OpenAI cache.
- Do not use `explicit` without sending an explicit breakpoint.
- Do not change tool definitions or structured schemas unnecessarily between
  otherwise identical requests.
- Do not make correct conversation behavior depend on a cache hit.
- Do not expect prompt caching to return the same response again.

See the
[OpenAI prompt caching guide](https://developers.openai.com/api/docs/guides/prompt-caching)
for current provider behavior and limits.

## Normative summary for AI agents

1. Prefer `toArray()` / `toJson()` and `fromArray()` / `fromJson()` for complete
   sessions.
2. Inject a new `ChatGPT` client and only the tools that are currently
   available when restoring a session.
3. Preserve historical `ChatOutput`, tool-call, and `ToolResult` data unchanged,
   and never execute historical calls again.
4. Register custom attachment decoders before deserialization.
5. For GPT-5.6, use implicit caching with a stable, versioned `promptCacheKey`
   unless explicit breakpoints have been implemented.
6. Detect cache hits only through `cachedTokens`; never assume them.
7. Persist after each relevant round and implement tools with side effects
   idempotently.

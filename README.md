# OpenAI ChatGPT API Client for PHP

Lightweight PHP client for OpenAI’s Chat Completions API with:
- Simple chat interface (`ChatGPT::chat`)
- Function calling (tools) with schema helpers
- Structured JSON responses validated against a JSON Schema
- Image inputs via URL
- Pluggable HTTP layer (bring your own client)

This library focuses on clear, composable building blocks that work well in typical PHP applications.

## Installation

- Library: `composer require rkr/openai-chatgpt-api`
- Optional (for examples below): `composer require guzzlehttp/guzzle nyholm/psr7`

Requirements: PHP 8.1+, `ext-json` and other common extensions (see `composer.json`).

## Quick Start (PSR-18)

Example with a PSR-18 HTTP client. You can plug in any PSR-18 implementation; here we use Guzzle plus Nyholm PSR-17 factories. No DI container needed.

```php
<?php

use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\OpenAIToken;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

require 'vendor/autoload.php';

$psr17 = new Psr17Factory();
$http = Psr18HttpClient::create(
    client: new GuzzleClient(),
    requestFactory: $psr17,
    streamFactory: $psr17,
);

$chat = new ChatGPT(
    token: new OpenAIToken(getenv('OPENAI_API_KEY') ?: ''),
    httpPostClient: $http,
);

$response = $chat->chat([
    ChatInput::mk('Write a short haiku about PHP.'),
]);

echo $response->firstChoice()->result, "\n";
```

## Core API: `ChatGPT::chat`

Signature (simplified):

- `chat(array $context, ?GPTFunctions $functions = null, ?JsonSchemaResponseFormat $responseFormat = null, ?ChatModelName $model = null, int $maxTokens = 2500, ?float $temperature = null, ?float $topP = null): ChatResponse`

Key concepts:
- Context is an array of chat messages (e.g., `ChatInput`, `ToolCall`, `ToolResult`).
- Optional `functions` enables tool/function-calling.
- Optional `responseFormat` enforces structured JSON responses.
- Optional `model` lets you choose a predefined or custom model name.

Minimal example using default model:

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;

$response = $chat->chat([
    new ChatInput('Summarize why typing helps in PHP 8.1.'),
]);
echo $response->firstChoice()->result;
```

Choose a model explicitly:

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallNoReasoning;   // gpt-5-mini
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;  // gpt-5.1

$response = $chat->chat(
    context: [new ChatInput('Explain traits in PHP.')],
    model: new LLMSmallNoReasoning(),
    maxTokens: 512,
);
```

Image input via URL:

```php
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

$response = $chat->chat([
    new ChatInput(
        content: 'Describe this image',
        attachment: new ChatImageUrl('https://example.com/cat.jpg')
    ),
]);

// ... or ...

$imgContents = file_get_contents('cat.jpg');
$b64 = base64_encode($imgContents);

$response = $chat->chat([
    new ChatInput(
        content: 'Describe this image',
        attachment: new ChatImageUrl("data:image/jpeg;base64,$b64")
    ),
]);
```

## Structured JSON Responses

Enforce structured output using a JSON Schema via `JsonSchemaResponseFormat`. The response is validated before returning, and `firstChoice()->result` is already decoded to an object.

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$schema = new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => [
        'items' => [
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'minItems' => 1,
        ],
    ],
    'additionalProperties' => false,
]);

$response = $chat->chat(
    context: [
        new ChatInput('Return the numbers 1..5 in a JSON object: {"items": [1,2,3,4,5]}'),
    ],
    responseFormat: $schema,
);

// When using a JSON schema, result is decoded JSON (object)
$data = $response->firstChoice()->result;
var_dump($data->items); // e.g., array(1,2,3,4,5)
```

Note about JSON decoding: Internally, `JSON::parse` returns objects (not associative arrays) so empty JSON objects `{}` and arrays `[]` remain distinguishable. This matters for tool-call arguments and schema-validated responses.

## Function Calling (Tools)

Describe callable tools with names, descriptions, and typed parameters. The model may choose to call them; you execute and feed results back into the context.

```php
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$context = [new ChatInput('What is the current temperature in Berlin? Answer in de-DE.')];

$functions = new GPTFunctions(
    new GPTFunction(
        name: 'get_temperature',
        description: 'Returns the current temperature from a given longitude and latitude.',
        properties: new GPTProperties(
            new GPTNumberProperty('longitude', 'The longitude of the location.', required: true),
            new GPTNumberProperty('latitude',  'The latitude of the location.',  required: true),
        ),
    ),
);

// 1) Let the model decide if it wants to call a tool
$response = $chat->chat(context: $context, functions: $functions);

foreach ($response->firstChoice()->tools as $tool) {
    if ($tool->functionName === 'get_temperature') {
        // Add the tool-call message to the context
        $context[] = $tool->toolCallMessage;

        // Execute your system/tool here and add the result
        $context[] = new ToolResult(
            toolCallId: $tool->id,
            content: ['temperature' => 21.2, 'unit' => 'celsius'],
        );
    }
}

// 2) Ask the model to continue with the new context
$response = $chat->chat(context: $context, functions: $functions);
echo $response->firstChoice()->result, "\n";
```

## Web Search

Use `ChatGPT::webSearch(string $query, ?array $userLocation = null, ?ChatModelName $model = null): WebSearchResponse` to run an OpenAI web search via the Responses API. It issues a `web_search` tool call and returns a `WebSearchResponse` you can inspect directly or feed back into chat. `model` is optional (defaults to `LLMMediumNoReasoning`).

- Optional `userLocation`: `['type' => 'exact|approximate', 'city' => string?, 'region' => string?, 'country' => string?, 'timezone' => string?]`
- Quick accessors: `getFirstText()` (throws if missing) and `tryGetFirstText()` (nullable)
- Full raw payload available on `$response->structure`

Example: search the web, then extract a typed value with a JSON schema

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

// 1) Perform a web search
$search = $chat->webSearch(
    query: 'What is the average weight of a baby elephant in kilograms?',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium),
);

// 2) Provide the search result back to chat and extract a number
$response = $chat->chat(
    context: [
        ChatInput::mk('What is the average weight of a baby elephant?'),
        ChatInput::mk($search->getFirstText()),
    ],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => [
            'weight' => ['type' => 'number'],
        ],
        'required' => ['weight'],
        'additionalProperties' => false,
    ]),
    model: new LLMMediumNoReasoning(),
    temperature: 0.1,
);

echo $response->firstChoice()->result->weight, "\n";
```

Using webSearch with reasoning models and effort

- You can pass a reasoning-capable model such as `LLMSmallReasoning`/`LLMMediumReasoning` to `webSearch`.
- When you do, the client includes `reasoning.effort` in the API request automatically based on the model.

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

$result = $chat->webSearch(
    query: 'Current heating oil prices in Berlin',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium)
);

echo $result->getFirstText();
```

Notes

- `webSearch` calls the Responses API with the `web_search` tool and returns the first completed message.
- If you pass a non-reasoning model, no `reasoning.effort` is sent.

Specialized chat-context classes: `WebSearchCall` and `WebSearchResult`

- Wrap a web search as a tool call plus tool result inside the chat context.
- Typical flow: create call, add to context, add result (text or texts), call `chat()` again.

Step-by-step from a `WebSearchResponse`

```php
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;

// 1) Run the search
$search = $chat->webSearch(
    query: 'Most important new features in PHP 8.3',
    userLocation: ['type' => 'approximate', 'country' => 'DE']
);

// 2) Create a tool call (ID is generated)
$call = $search->getWebSearchRequest();

// 3) Derive a matching tool result (includes metadata)
$result = $search->getWebSearchResponse();

// 4) Add both to context and continue chatting
$ctx = [$call, $result];
$response = $chat->chat(context: $ctx);
```

Manual creation without a prior `WebSearchResponse`

```php
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;

// 1) Create your own ID to link call and result
$callId = 'web_' . bin2hex(random_bytes(6));

// 2) Build the tool call with query and optional params
$call = new WebSearchCall(
    $callId,
    'Wichtigste Neuerungen in PHP 8.3',
    ['type' => 'approximate', 'country' => 'DE'], // userLocation (optional)
    'gpt-5.1',                                   // model (optional)
    'medium'                                      // effort (optional: low|medium|high)
);

// 3) Add result text(s) (metadata optional)
$result = WebSearchResult::fromText($callId, 'PHP 8.3 bringt u.a. ...', [
    'query' => 'Wichtigste Neuerungen in PHP 8.3',
    'model' => 'gpt-5.1',
    'effort' => 'medium',
    'user_location' => ['type' => 'approximate', 'country' => 'DE'],
]);

// 4) Build context and call chat()
$ctx = [$call, $result];
$response = $chat->chat(context: $ctx);
```

Inline web search as a function tool (no separate `webSearch()` call)

`buildWebSearchFunction()` returns a callable `web_search` tool you can pass via `GPTFunctions`. When the model calls it, the client performs the search and feeds the result back into context automatically.

```php
$webSearchTool = $chat->buildWebSearchFunction();
$response = $chat->chat(
    context: [ChatInput::mk('Find the net weight of "Babyliss Pro GUNSTEELFX FX7870GSE" in kg and answer directly.')],
    functions: new GPTFunctions($webSearchTool),
);

echo $response->firstChoice()->result;
```

## Text to Speech

Generate audio with `textToSpeech`. The call returns raw audio bytes (e.g., WAV) that you can persist.

```php
use DvTeam\ChatGPT\PredefinedModels\TextToSpeech\GPT4oMiniTextToSpeech;

$audio = $chat->textToSpeech(
    text: 'Hallo Welt!',
    voice: 'alloy',
    speed: 1.0,
    instructions: 'Klingt verhalten aufgeregt.',
    model: new GPT4oMiniTextToSpeech(),
    format: 'wav',
);

file_put_contents('/tmp/example.wav', $audio);
```

## Notes

- The library converts image inputs into the Chat Completions message format automatically.
- When a JSON schema is supplied, responses are validated using `opis/json-schema`. Invalid responses throw an exception. 
  - You can bring your own JSON schema validator by implementing `JsonSchemaValidatorInterface`.
- `ChatResponse::firstChoice()` is a convenience for the first returned choice.

## Reasoning models and effort

Preset reasoning models let you specify `effort` (`low`, `medium`, `high`, see `ReasoningEffort` enum).

- `LLMSmallReasoning` maps to `gpt-5-mini` with configurable effort.
- `LLMMediumReasoning` maps to `gpt-5.1` with configurable effort.
- Custom: `LLMCustomModel($model, effort: ?ReasoningEffort)`.

When a reasoning-capable model (or custom with effort) is used, the client automatically sets `reasoning.effort` for both `chat()` and `webSearch()`.

Examples

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;   // gpt-5-mini
use DvTeam\ChatGPT\PredefinedModels\LLMMediumReasoning;  // gpt-5.1
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

// Small reasoning model with medium effort
$r1 = $chat->chat(
    context: [new ChatInput('Briefly explain how traits work in PHP.')],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium)
);

// Medium reasoning model with high effort
$r2 = $chat->chat(
    context: [new ChatInput('Design a robust error-handling strategy for a REST API in PHP.')],
    model: new LLMMediumReasoning(effort: ReasoningEffort::High)
);

// Custom model with explicit effort
$r3 = $chat->chat(
    context: [new ChatInput('Provide a bullet summary.')],
    model: new LLMCustomModel('gpt-5.1', effort: ReasoningEffort::Low)
);

echo $r1->firstChoice()->result, "\n";
```

## Maintenance

Whenever new functionality is added, update both `README.md` and `AGENTS.md` so human- and agent-facing docs stay aligned.

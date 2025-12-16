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
- Optional (for examples below): `composer require guzzlehttp/guzzle`

Requirements: PHP 8.1+, `ext-json` and other common extensions (see `composer.json`).

## Quick Start

Example with Guzzle as the HTTP client. You can implement `HttpPostInterface` with any HTTP library.

```php
<?php

use GuzzleHttp\Client;
use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Http\HttpPostInterface;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\OpenAIToken;

require 'vendor/autoload.php';

$client = new Client();

$http = new class($client) implements HttpPostInterface {
    public function __construct(private Client $client) {}
    public function post(string $url, array $data, array $headers): string {
        $response = $this->client->post($url, [
            'json'    => $data,
            'headers' => $headers,
        ]);
        return $response->getBody()->getContents();
    }
};

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
    temperature: 0.7,
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
```

## Structured JSON Responses

Enforce structured output using a JSON Schema via `JsonSchemaResponseFormat`. The library validates the response before returning it.

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$response = $chat->chat(
    context: [
        new ChatInput('Return the numbers 1..5 in a JSON object: {"items": [1,2,3,4,5]}'),
    ],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => ['type' => 'integer']
            ],
        ],
        'additionalProperties' => false,
    ])
);

// When using a JSON schema, result is decoded JSON (object)
$data = $response->firstChoice()->result;
var_dump($data->items); // e.g., array(1,2,3,4,5)
```

Note about JSON decoding: Internally, `JSON::parse` returns objects (not associative arrays) so empty JSON objects `{}` and arrays `[]` remain distinguishable. This matters for tool-call arguments and schema-validated responses.

## Function Calling (Tools)

Describe callable tools with names, descriptions, and typed parameters. The model may choose to call them, returning tool calls you can execute and then respond to.

```php
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;

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
            name: $tool->functionName,
            content: ['temperature' => 21.2, 'unit' => 'celsius'],
        );
    }
}

// 2) Ask the model to continue with the new context
$response = $chat->chat(context: $context, functions: $functions);
echo $response->firstChoice()->result, "\n";
```

## Web Search

Use `ChatGPT::webSearch(string $query, ?array $userLocation = null, ?ChatModelName $model = null): WebSearchResponse` to run an OpenAI web search via the Responses API. It issues a `web_search` tool call and returns a `WebSearchResponse` that you can inspect directly or feed back into a regular chat. The `model` is optional and follows the same pattern as `chat` (defaults to `LLMMediumNoReasoning`, i.e., `gpt-5.1`).

- Optional `userLocation`: `['type' => 'exact|approximate', 'city' => string?, 'region' => string?, 'country' => string?, 'timezone' => string?]`
- Quick accessors: `getFirstText()` (throws if missing) and `tryGetFirstText()` (nullable)
- Full raw payload available on `$response->structure`

Example: search the web, then extract a typed value with a JSON schema

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMImageToText; // maps to gpt-5
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

// 1) Perform a web search
$search = $chat->webSearch(
    query: 'What is the net weight of "Babyliss Pro GUNSTEELFX FX7870GSE" in kilograms?',
    userLocation: [
        'type' => 'approximate',
        'country' => 'DE', // optional hints for localization
    ],
    model: new LLMImageToText(), // optional; choose a model explicitly
);

// 2) Provide the search result back to chat and extract a number
$response = $chat->chat(
    context: [
        ChatInput::mk('Return the product weight in kilograms as JSON.'),
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
    query: 'Aktuelle Heizölpreise in Berlin',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium)
);

echo $result->getFirstText();
```

Notes

- `webSearch` calls the Responses API with the `web_search` tool and returns the first completed message.
- If you pass a non-reasoning model, no `reasoning.effort` is sent.

Spezifische Klassen für den Chat-Context: WebSearchCall und WebSearchResult

- `WebSearchCall` und `WebSearchResult` sind spezialisierte Klassen, um eine Websuche im Chat-Kontext als Tool-Call samt Ergebnis sauber abzubilden.
- Typischer Ablauf: Tool-Call erzeugen, ins Context-Array legen, Ergebnis (Text oder Texte) als Tool-Result hinzufügen, mit `chat()` fortfahren.

Schritt-für-Schritt aus einer WebSearchResponse

```php
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;

// 1) Suche ausführen
$search = $chat->webSearch(
    query: 'Wichtigste Neuerungen in PHP 8.3',
    userLocation: ['type' => 'approximate', 'country' => 'DE']
);

// 2) Dedizierten Tool-Call erzeugen (ID wird generiert)
$call = $search->getWebSearchCall();

// 3) Passendes Tool-Result aus der Response ableiten (inkl. Metadaten)
$result = $search->getWebSearchResult($call->id);

// 4) Beides in den Chat-Context geben und das Modell fortfahren lassen
$ctx = [$call, $result];
$response = $chat->chat(context: $ctx);
```

Manuelle Erstellung ohne vorherige WebSearchResponse

```php
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;

// 1) Eigene ID erzeugen, um Call und Result zu verknüpfen
$callId = 'web_' . bin2hex(random_bytes(6));

// 2) Tool-Call mit Query und optionalen Parametern anlegen
$call = new WebSearchCall(
    $callId,
    'Wichtigste Neuerungen in PHP 8.3',
    ['type' => 'approximate', 'country' => 'DE'], // userLocation (optional)
    'gpt-5.1',                                   // model (optional)
    'medium'                                      // effort (optional: low|medium|high)
);

// 3) Tool-Result mit Text oder Texten hinzufügen (inkl. Metadaten möglich)
$result = WebSearchResult::fromText($callId, 'PHP 8.3 bringt u.a. ...', [
    'query' => 'Wichtigste Neuerungen in PHP 8.3',
    'model' => 'gpt-5.1',
    'effort' => 'medium',
    'user_location' => ['type' => 'approximate', 'country' => 'DE'],
]);

// 4) Context aufbauen und chat() aufrufen
$ctx = [$call, $result];
$response = $chat->chat(context: $ctx);
```

## Notes

- The library converts image inputs into the Chat Completions message format automatically.
- When a JSON schema is supplied, responses are validated using `opis/json-schema`. Invalid responses throw an exception. 
  - You can bring your own JSON schema validator by implementing `JsonSchemaValidatorInterface`.
- `ChatResponse::firstChoice()` is a convenience for the first returned choice.

## Reasoning-Modelle und Effort

Die Bibliothek bringt vordefinierte Reasoning-Modelle mit, bei denen du die gewünschte Reasoning-Intensität per `effort` festlegst. Unterstützte Werte sind `low`, `medium`, `high` (siehe `ReasoningEffort` Enum).

- `LLMSmallReasoning` entspricht `gpt-5-mini` mit einstellbarem `effort`.
- `LLMMediumReasoning` entspricht `gpt-5.1` mit einstellbarem `effort`.
- Für eigene Modelle: `LLMCustomModel($model, effort: ?ReasoningEffort)`.

Wenn ein Reasoning‑Modell (oder ein Custom‑Modell mit gesetztem `effort`) verwendet wird, setzt der Client automatisch das Feld `reasoning.effort` im Request – sowohl bei `chat()` als auch bei `webSearch()`.

Beispiele

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;   // gpt-5-mini
use DvTeam\ChatGPT\PredefinedModels\LLMMediumReasoning;  // gpt-5.1
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

// Kleines Reasoning-Modell mit mittlerem Effort
$r1 = $chat->chat(
    context: [new ChatInput('Erkläre kurz, wie Traits in PHP funktionieren.')],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium)
);

// Mittelgroßes Reasoning-Modell mit hohem Effort
$r2 = $chat->chat(
    context: [new ChatInput('Entwirf eine robuste Fehlerbehandlungsstrategie für eine REST-API in PHP.')],
    model: new LLMMediumReasoning(effort: ReasoningEffort::High)
);

// Eigenes Modell mit explizitem Effort
$r3 = $chat->chat(
    context: [new ChatInput('Gib eine stichpunktartige Zusammenfassung aus.')],
    model: new LLMCustomModel('gpt-5.1', effort: ReasoningEffort::Low)
);

echo $r1->firstChoice()->result, "\n";
```

## License

MIT

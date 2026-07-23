# Getting Started

This page covers client setup and single-request use through `ChatGPT::chat()`.
For stateful sessions, see [Conversations, Persistence, and Prompt
Caching](conversations.md).

## Installation

Install the library and a PSR-18/PSR-17 implementation:

```bash
composer require dv-team/openai-chatgpt-api
composer require guzzlehttp/guzzle nyholm/psr7
```

The library requires PHP 8.1 or newer. See `composer.json` for the complete
extension list.

## Create a client

`Psr18HttpClient` adapts any PSR-18 client and PSR-17 request/stream factories:

```php
<?php

use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
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
```

Application code should reject an empty API key before sending requests. The
executable files under `examples/` perform that check in `examples/_bootstrap.php`.

## Send one text request

`ChatGPT::chat()` is stateless: pass the complete context and receive one model
response.

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;

$response = $chat->chat([
    new ChatInput('Summarize why typing helps in PHP 8.1.'),
]);

echo $response->firstChoice()->result;
```

Its simplified signature is:

```php
chat(
    array $context,
    ?GPTFunctions $functions = null,
    ?JsonSchemaResponseFormat $responseFormat = null,
    ?ChatModelName $model = null,
    int $maxTokens = 2500,
    ?float $temperature = null,
    ?float $topP = null,
    ?string $promptCacheKey = null,
    ?PromptCacheOptions $promptCacheOptions = null,
): ChatResponse
```

The low-level client:

- sends exactly one Responses API request;
- does not retain state between calls;
- does not execute tool calls automatically;
- accepts `ChatInput`, `ChatOutput`, `ToolResult`, and other `ChatMessage`
  implementations as context;
- optionally applies tool definitions and a structured response schema.

`ChatModelName` implementations announce whether they support
`temperature`, `top_p`, and `max_output_tokens`. `ChatGPT::chat()` sends each
parameter only when the selected model reports support for it.

## Start a stateful conversation

Use `GPTConversation` when the client should maintain context:

```php
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

$conversation = new GPTConversation(
    chat: $chat,
    context: [ChatInput::mk('Write a short haiku about PHP.')],
);

$reply = $conversation->step();

echo $reply->result, "\n";
```

Continue with [the conversation guide](conversations.md) for follow-ups,
automatic tool rounds, persistence, restoration, and prompt caching.

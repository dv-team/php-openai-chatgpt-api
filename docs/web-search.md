# Web Search

The client supports direct hosted search, insertion of search calls and results
into a context, and model-selected search through `GPTConversation`.

The examples assume that `$chat` is initialized as described in
[Getting Started](getting-started.md).

## Direct search and structured extraction

`ChatGPT::webSearch()` issues a Responses API request with the hosted
`web_search` tool and returns a `WebSearchResponse`.

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$search = $chat->webSearch(
    query: 'What is the average weight of a baby elephant in kilograms?',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(ReasoningEffort::Medium),
);

$response = $chat->chat(
    context: [
        ChatInput::mk('Extract the average baby-elephant weight.'),
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

The optional location has this shape:

```php
[
    'type' => 'exact', // or approximate
    'city' => 'Berlin',
    'region' => 'Berlin',
    'country' => 'DE',
    'timezone' => 'Europe/Berlin',
]
```

Use `getFirstText()` when a missing result should throw, or
`tryGetFirstText()` for a nullable result. The complete provider response is
available through `$search->structure`.

## Search with a reasoning model

Reasoning-capable model presets send their configured reasoning effort:

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

$result = $chat->webSearch(
    query: 'Current heating oil prices in Berlin',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(ReasoningEffort::Medium),
);

echo $result->getFirstText();
```

GPT-5.6 no-reasoning presets explicitly send `reasoning.effort: none`.

## Put a search response into chat context

`WebSearchResponse` can produce a matching request/result pair:

```php
$search = $chat->webSearch(
    query: 'Most important new features in PHP 8.3',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
);

$call = $search->getWebSearchRequest();
$result = $search->getWebSearchResponse();

$context = [$call, $result];
$response = $chat->chat(context: $context);
```

The pair can also be created manually:

```php
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;

$callId = 'web_' . bin2hex(random_bytes(6));

$call = new WebSearchCall(
    id: $callId,
    query: 'Most important new features in PHP 8.3',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: 'gpt-5.6-sol',
    effort: 'medium',
);

$result = WebSearchResult::fromText(
    toolCallId: $callId,
    text: 'PHP 8.3 includes typed class constants and other changes.',
    extra: [
        'query' => 'Most important new features in PHP 8.3',
        'model' => 'gpt-5.6-sol',
        'effort' => 'medium',
        'user_location' => ['type' => 'approximate', 'country' => 'DE'],
    ],
);

$context = [$call, $result];
$response = $chat->chat(context: $context);
```

## Model-selected search in a conversation

`buildCallableWebSearchTool()` creates an executable tool. The model decides
when to search, and `GPTConversation` appends the search result to its context:

```php
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

$model = new LLMMediumReasoning(ReasoningEffort::Medium);

$conversation = new GPTConversation(
    chat: $chat,
    context: [
        ChatInput::mk(
            'Research which firmware questions matter when buying this used drone.',
        ),
    ],
    callableTools: [
        $chat->buildCallableWebSearchTool(
            defaultUserLocation: ['type' => 'approximate', 'country' => 'DE'],
            defaultModel: $model,
        ),
    ],
    model: $model,
);

$response = $conversation->runUntilResponse(maxSteps: 8);

echo $response->textResult;
```

`buildWebSearchFunction()` creates only the schema for a manually managed
low-level tool loop. It is not an executable `GPTConversation` tool.

## Used-product interview CLI

[`examples/09-used-product-interview.php`](../examples/09-used-product-interview.php)
combines a long-running STDIN session, model-selected web search, local
final-data validation, and prompt caching:

```bash
OPENAI_API_KEY="..." php examples/09-used-product-interview.php
```

See [Conversations, Persistence, and Prompt Caching](conversations.md) for the
session lifecycle used by this example.

Usage guide for agents working with this PHP OpenAI ChatGPT client. Examples below are minimal snippets you can paste into your own environment; do not rely on absent example files.

Setup
- Low-level client: `DvTeam\ChatGPT\ChatGPT` in `src/ChatGPT.php` (one API call, no recursion).
- Conversation helper: `DvTeam\ChatGPT\GPTConversation` manages context/tools step-by-step.
- Default model if none is passed: `LLMMediumNoReasoning`.
  - `LLMLargeNoReasoning` and `LLMLargeReasoning` map to `gpt-5.6-sol`.
  - `LLMMediumNoReasoning`, `LLMMediumReasoning`, `LLMSmallNoReasoning`, and `LLMSmallReasoning` map to `gpt-5.6-terra`.
  - `LLMNanoNoReasoning` maps to `gpt-5.6-luna`.
  - Large, Medium, Small, and Nano represent cost categories; Medium and Small currently use the same model.
  - The `NoReasoning` presets explicitly send `reasoning.effort: none`; GPT-5.6 would otherwise default to medium.
  - Reasoning presets support `none`, `low`, `medium`, `high`, `xhigh`, and `max`.
- `ChatModelName` models announce support for tunables via:
  - `supportsTemperature(): bool`
  - `supportsTopP(): bool`
  - `supportsMaxTokens(): bool`
- `ChatGPT::chat()` only includes `temperature`, `top_p`, and `max_output_tokens` in API requests when the chosen model reports support.
- Use PSR-18 with the built-in adapter `src/Http/Psr18HttpClient.php` (no DI container needed):

```php
use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\OpenAIToken;
use GuzzleHttp\Client as GuzzleClient; // PSR-18 client
use Nyholm\Psr7\Factory\Psr17Factory;  // PSR-17 request/stream

$token = new OpenAIToken(getenv('OPENAI_API_KEY') ?: '');
$http = Psr18HttpClient::create(
    client: new GuzzleClient(),
    requestFactory: new Psr17Factory(),
    streamFactory: new Psr17Factory(),
);
$chat = new ChatGPT(token: $token, httpPostClient: $http);
```

Attachments (e.g. images)

- `ChatInput` supports `attachment: ChatAttachment` and will append `toInputContentParts()` to the message content.
- For context persistence/rehydration (`contextAsArray` / `contextFromArray`), the attachment should also implement `ContextSerializable` and you must register its serialized `type` via `ChatInput::registerAttachmentType(...)`.

Basic chat and follow-ups

```php
$conversation = new GPTConversation($chat, [new ChatInput('Was ist die Hauptstadt von Frankreich?')]);
$reply = $conversation->step(); // one API call

$conversation->addMessage(new ChatInput('Wie viele Einwohner hat diese Stadt?'));
$followUp = $conversation->step();
echo $followUp->result;
```

For agent-style rounds, use `$conversation->runUntilResponse(maxSteps: 8)`. It executes callable tools and contacts the API again until a visible response arrives, but throws after the configured bound instead of allowing an endless tool loop. `step(true)` is the eight-step convenience form.

Callable parameter names are exposed to the model as `snake_case` and mapped back to their original PHP names during invocation. For example, `$productJson` is published and accepted as `product_json`.

`ChatOutput` retains raw Responses API output items. Keep them when persisting context: they preserve GPT-5.6 reasoning items, IDs, and function-call metadata required for lossless multi-round replay.

Complete conversation session <-> JSON

```php
use DvTeam\ChatGPT\Common\PromptCacheOptions;

$conversation = new GPTConversation(
    chat: $chat,
    context: [new ChatInput('Start a longer session.')],
    callableTools: [$currentTool],
    model: $model,
    promptCacheKey: 'application:v1:session-123',
    promptCacheOptions: new PromptCacheOptions(),
);

$json = $conversation->toJson();
$conversation = GPTConversation::fromJson(
    chat: $chat,
    json: $json,
    tools: [$currentlyAvailableTool],
);
```

- `toArray()` / `toJson()` persist context, effective model capabilities and reasoning effort, response format, token/sampling settings, and prompt-cache configuration.
- `fromArray()` / `fromJson()` require a `ChatGPT` client and accept the current tool list separately. Historical tool calls do not require their original PHP callables.
- `PromptCacheOptions` supports `implicit` / `explicit` and the GPT-5.6 `30m` TTL. Reuse a stable `promptCacheKey` for requests with the same prefix.
- `ChatResponse` and `ChatResponseChoice` expose the root response ID and `ResponseUsage`, including `cachedTokens` and `cacheWriteTokens`.
- `serialize()` / `fromSerialized()` remain the context-only compatibility API.

Context <-> Array (context only)

```php
$payload = ChatGPT::contextAsArray($conversation->getContext()); // array<int, array<string, mixed>>
$context = ChatGPT::contextFromArray($payload);                 // message objects again
```

Structured JSON responses

```php
$schema = new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1]],
]);
$conversation = new GPTConversation($chat, [new ChatInput('Erstelle eine Liste: vier Einsen, dann zwei Zweien.')], responseFormat: $schema);
$first = $conversation->step();
$conversation->addMessage(new ChatInput('Füge der Liste drei 3 am Ende hinzu.'));
$second = $conversation->step();
print_r($second->result->items); // -> [1,1,1,1,2,2,3,3,3]
```

Structured with reasoning

```php
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
$res = $chat->chat(
    context: [new ChatInput('Vier Einsen, dann zwei Zweien.')],
    responseFormat: $schema,
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium),
);
```

Tool calling with multiple rounds

```php
$context = [new ChatInput('Find the Number for A and the Number for C.')];

$getNumber = new GPTFunctions(new GPTFunction(
    name: 'get_number_by_letter',
    description: 'Returns a number for a single letter.',
    properties: new GPTProperties(new GPTStringProperty('letter', 'Letter to map', required: true))
));

$step1 = $chat->chat(context: $context, functions: $getNumber)->firstChoice(); // one API call
$context[] = $step1->getChatOutput(); // includes tool calls in context
foreach ($step1->tools as $tool) {
    $args = $tool->arguments;
    $context[] = new ToolResult($tool->id, match($args->letter) { 'A' => 1, 'B' => 2, 'C' => 3, default => null });
}

$getWord = new GPTFunctions(new GPTFunction(
    name: 'get_a_word_by_number',
    description: 'Returns a word for a single number.',
    properties: new GPTProperties(new GPTNumberProperty('number', 'The number.', required: true))
));
$context[] = new ChatInput('Get the word for the numbers using tool get_a_word_by_number.');
$step2 = $chat->chat(context: $context, functions: $getWord)->firstChoice();
$context[] = $step2->getChatOutput(); // includes tool calls in context
foreach ($step2->tools as $tool) {
    $n = $tool->arguments->number;
    $context[] = new ToolResult($tool->id, match($n) { 1 => 'Sun', 2 => 'Moon', 3 => 'Earth' });
}
$context[] = new ChatInput('What are the two words?');
$responseFormat = new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => ['first_word' => ['type' => 'string'], 'second_word' => ['type' => 'string']],
]);
$final = $chat->chat(context: $context, functions: $getWord, responseFormat: $responseFormat)->firstChoice();
echo JSON::stringify($final->result);
```

Web search flow

```php
$search = $chat->webSearch(
    query: 'Wie schwer ist das Produkt "Babyliss Pro GUNSTEELFX FX7870GSE" ohne Verpackung in Kilogramm?',
    userLocation: ['type' => 'approximate', 'country' => 'DE'],
    model: new LLMSmallReasoning(effort: ReasoningEffort::Medium),
);
$response = $chat->chat(
    context: [
        ChatInput::mk('Wie schwer ist das Produkt ...?'),
        ChatInput::mk($search->getFirstText()),
    ],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => ['weight' => ['type' => 'number']],
    ]),
    model: new LLMMediumNoReasoning(),
    temperature: 0.1,
);
echo $response->firstChoice()->result->weight;
```

Helpers from `WebSearchResponse`: `getWebSearchRequest()` returns a `WebSearchCall`, `getWebSearchResponse()` returns a matching `WebSearchResult` if you prefer to embed search calls/results directly in the chat context.

For model-selected search inside a conversation:

```php
$model = new LLMMediumReasoning(ReasoningEffort::Medium);
$conversation = new GPTConversation(
    chat: $chat,
    context: [ChatInput::mk('Research this product before answering.')],
    callableTools: [
        $chat->buildCallableWebSearchTool(
            defaultUserLocation: ['type' => 'approximate', 'country' => 'DE'],
            defaultModel: $model,
        ),
    ],
    model: $model,
);
$reply = $conversation->runUntilResponse(maxSteps: 8);
```

`buildCallableWebSearchTool()` is executable by `GPTConversation`. `buildWebSearchFunction()` only creates a schema for manual low-level tool loops.

Text to speech

```php
$audio = $chat->textToSpeech(
    text: 'Hallo Welt!',
    voice: 'alloy',
    speed: 1.0,
    instructions: 'Klingt verhalten aufgeregt.',
    model: new GPT4oMiniTextToSpeech(),
    format: 'wav',
);
file_put_contents('/tmp/test.wav', $audio);
```

Used-product interview demo

- `examples/09-used-product-interview.php` is the long-session STDIN example.
- It uses GPT-5.6 Sol with medium reasoning, model-selected web search, prompt caching, one-question-at-a-time interviewing, a locally validated final-submission tool, and bounded tool rounds.
- Run with `php examples/09-used-product-interview.php` after setting `OPENAI_API_KEY`.

Example: `test-tool-function-calling.php`

- Shows two-step tool calling driven by PHP attributes instead of manual schemas.
- First round maps letters to numbers; second maps those numbers to words; both responses are validated with JSON schemas.
- Run with `php test-tool-function-calling.php`.

```php
use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$conversation = new GPTConversation(
    $chat,
    [new ChatInput('Find the Number for A and the Number for C.')],
    callableTools: [
        #[GPTCallableDescriptor(name: 'get_number_by_letter', description: 'Returns a number for a single letter.')]
        function (#[GPTParameterDescriptor(['description' => 'Letter to map.'])] string $letter): ?int {
            return match($letter) { 'A' => 1, 'B' => 2, 'C' => 3, default => null };
        }
    ],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => ['numbers' => ['type' => 'array', 'items' => ['type' => 'integer']]],
    ]),
);

$conversation->step(); // one API call, tool executed locally

$conversation->setTools([
    #[GPTCallableDescriptor(name: 'get_word_by_number', description: 'Returns a word by a single number.')]
    function (#[GPTParameterDescriptor(['description' => 'Number to map.'])] int $number): ?string {
        return match($number) { 1 => 'Sun', 2 => 'Moon', 3 => 'Earth', default => null };
    }
]);
$conversation->addMessage(new ChatInput('Get a word for each number using tool get_word_by_number.'));
$conversation->setResponseFormat(new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => ['words' => ['type' => 'array', 'items' => ['type' => 'string']]],
]));
$final = $conversation->step();

print_r($final->result->words);
```

Maintenance rule

- Every time new functionality is added, update both `README.md` and `AGENTS.md` to keep human- and agent-facing docs aligned.
- Add Unit-tests if useful.

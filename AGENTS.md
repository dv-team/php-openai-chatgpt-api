Usage guide for agents working with this PHP OpenAI ChatGPT client. Examples below are minimal snippets you can paste into your own environment; do not rely on absent example files.

Setup
- Low-level client: `DvTeam\ChatGPT\ChatGPT` in `src/ChatGPT.php` (one API call, no recursion).
- Conversation helper: `DvTeam\ChatGPT\GPTConversation` manages context/tools step-by-step.
- Default model if none is passed: `LLMMediumNoReasoning`.
  - `\DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning` translates to the large models like `gpt-5.1`.
  - `\DvTeam\ChatGPT\PredefinedModels\LLMSmallNoReasoning` translates to the mini models like `gpt-5.1-mini`.
  - `\DvTeam\ChatGPT\PredefinedModels\LLMNanoNoReasoning` translates to the nano models like `gpt-5.1-nano`.
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

Basic chat and follow-ups

```php
$conversation = new GPTConversation($chat, [new ChatInput('Was ist die Hauptstadt von Frankreich?')]);
$reply = $conversation->step(); // one API call

$conversation->addMessage(new ChatInput('Wie viele Einwohner hat diese Stadt?'));
$followUp = $conversation->step();
echo $followUp->result;
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
$getNumber = new GPTFunctions(new GPTFunction(
    name: 'get_number_by_letter',
    description: 'Returns a number for a single letter.',
    properties: new GPTProperties(new GPTStringProperty('letter', 'Letter to map', required: true))
));
$conversation = new GPTConversation($chat, [new ChatInput('Find the Number for A und and the Number for C.')], $getNumber);
$step1 = $conversation->step(); // one API call
foreach ($step1->tools as $tool) {
    $args = $tool->arguments;
    $conversation->addMessage(new ToolResult($tool->id, match($args->letter) { 'A' => 1, 'B' => 2, 'C' => 3, default => null }));
}

$getWord = new GPTFunctions(new GPTFunction(
    name: 'get_a_word_by_number',
    description: 'Returns a word for a single number.',
    properties: new GPTProperties(new GPTNumberProperty('number', 'The number.', required: true))
));
$conversation->setFunctions($getWord);
$conversation->addMessage(new ChatInput('Get the word for the numbers using tool get_a_word_by_number.'));
$step2 = $conversation->step();
foreach ($step2->tools as $tool) {
    $n = $tool->arguments->number;
    $conversation->addMessage(new ToolResult($tool->id, match($n) { 1 => 'Sun', 2 => 'Moon', 3 => 'Earth' }));
}
$conversation->addMessage(new ChatInput('What are the two words?'));
$conversation->setResponseFormat(new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => ['first_word' => ['type' => 'string'], 'second_word' => ['type' => 'string']],
]));
$final = $conversation->step();
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

Example: `test-tool-function-calling.php`

- Shows two-step tool calling driven by PHP attributes instead of manual schemas.
- First round maps letters to numbers; second maps those numbers to words; both responses are validated with JSON schemas.
- Run with `php test-tool-function-calling.php`.

```php
use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\Functions\CallableGPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$conversation = new GPTConversation(
    $chat,
    [new ChatInput('Find the Number for A and the Number for C.')],
    new GPTFunctions(
        new CallableGPTFunction(
            #[GPTCallableDescriptor(name: 'get_number_by_letter', description: 'Returns a number for a single letter.')]
            function (#[GPTParameterDescriptor(description: 'Letter to map.')] string $letter): ?int {
                return match($letter) { 'A' => 1, 'B' => 2, 'C' => 3, default => null };
            }
        )
    ),
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => ['numbers' => ['type' => 'array', 'items' => ['type' => 'integer']]],
    ]),
);

$conversation->step(); // one API call, tool executed locally

$conversation->setFunctions(new GPTFunctions(
    new CallableGPTFunction(
        #[GPTCallableDescriptor(name: 'get_word_by_number', description: 'Returns a word by a single number.')]
        function (#[GPTParameterDescriptor(description: 'Number to map.')] int $number): ?string {
            return match($number) { 1 => 'Sun', 2 => 'Moon', 3 => 'Earth', default => null };
        }
    )
));
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

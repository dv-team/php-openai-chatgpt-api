# Structured JSON Responses

`JsonSchemaResponseFormat` instructs the model to return JSON matching a schema.
The client validates the response before returning it and exposes the decoded
result as an object.

The examples assume that `$chat` is initialized as described in
[Getting Started](getting-started.md).

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
    'required' => ['items'],
    'additionalProperties' => false,
]);

$response = $chat->chat(
    context: [
        new ChatInput('Return the integers from 1 through 5.'),
    ],
    responseFormat: $schema,
);

$data = $response->firstChoice()->result;
var_dump($data->items);
```

The same response format can be attached to `GPTConversation`:

```php
$conversation = new GPTConversation(
    chat: $chat,
    context: [new ChatInput('Return four ones followed by two twos.')],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
            ],
        ],
        'required' => ['items'],
        'additionalProperties' => false,
    ]),
);

$first = $conversation->step();

$conversation->addMessage(new ChatInput('Append three threes.'));
$second = $conversation->step();

print_r($second->result->items);
```

Use `setResponseFormat()` when later rounds need a different schema:

```php
$conversation->setResponseFormat($nextSchema);
```

Internally, `JSON::parse()` returns objects rather than associative arrays.
This preserves the difference between an empty JSON object (`{}`) and an empty
JSON array (`[]`). The distinction matters for tool arguments and validated
responses.

The response schema is part of a serialized full conversation and also
contributes to the prompt prefix used for prompt caching. Changing it can
reduce cache reuse.

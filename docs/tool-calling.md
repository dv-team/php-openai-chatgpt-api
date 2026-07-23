# Tool Calling

Tools let the model request application code instead of inventing data. This
client supports both manual low-level tool loops and executable PHP callables
managed by `GPTConversation`.

The examples assume that `$chat` is initialized as described in
[Getting Started](getting-started.md).

## Manual low-level tool loop

Define the schema, send one request, execute the requested operation, append its
result, and send the updated context:

```php
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;

$context = [
    new ChatInput('What is the current temperature in Berlin? Answer in de-DE.'),
];

$functions = new GPTFunctions(
    new GPTFunction(
        name: 'get_temperature',
        description: 'Returns the current temperature for coordinates.',
        properties: new GPTProperties(
            new GPTNumberProperty(
                'longitude',
                'Longitude of the location.',
                required: true,
            ),
            new GPTNumberProperty(
                'latitude',
                'Latitude of the location.',
                required: true,
            ),
        ),
    ),
);

$response = $chat->chat(context: $context, functions: $functions);
$choice = $response->firstChoice();

// Preserve the complete assistant output, including raw tool-call metadata.
$context[] = $choice->getChatOutput();

foreach($choice->tools as $tool) {
    if($tool->name === 'get_temperature') {
        $context[] = new ToolResult(
            toolCallId: $tool->id,
            content: ['temperature' => 21.2, 'unit' => 'celsius'],
        );
    }
}

$response = $chat->chat(context: $context, functions: $functions);

echo $response->firstChoice()->result, "\n";
```

The low-level client never executes the tool. The application must validate the
name and arguments, perform the operation, and connect `ToolResult` to the
request through the tool-call ID.

## Callable tools in a conversation

`GPTConversation` derives tool schemas from PHP attributes and executes matching
callables locally. PHP parameter names are exposed to the model in
`snake_case` and mapped back to their PHP names during invocation.

This example changes both the available tools and the structured response
format between rounds:

```php
use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;

$conversation = new GPTConversation(
    chat: $chat,
    context: [
        new ChatInput('Find the numbers for A and C.'),
    ],
    callableTools: [
        #[GPTCallableDescriptor(
            name: 'get_number_by_letter',
            description: 'Returns a number for one letter.',
        )]
        function(
            #[GPTParameterDescriptor(['description' => 'Letter to map.'])]
            string $letter,
        ): ?int {
            return match($letter) {
                'A' => 1,
                'B' => 2,
                'C' => 3,
                default => null,
            };
        },
    ],
    responseFormat: new JsonSchemaResponseFormat([
        'type' => 'object',
        'properties' => [
            'numbers' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
            ],
        ],
        'required' => ['numbers'],
        'additionalProperties' => false,
    ]),
);

// One API call. Requested tools are executed and their results are retained.
$conversation->step();

$conversation->setTools([
    #[GPTCallableDescriptor(
        name: 'get_word_by_number',
        description: 'Returns a word for one number.',
    )]
    function(
        #[GPTParameterDescriptor(['description' => 'Number to map.'])]
        int $number,
    ): ?string {
        return match($number) {
            1 => 'Sun',
            2 => 'Moon',
            3 => 'Earth',
            default => null,
        };
    },
]);

$conversation->setResponseFormat(new JsonSchemaResponseFormat([
    'type' => 'object',
    'properties' => [
        'words' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ],
    ],
    'required' => ['words'],
    'additionalProperties' => false,
]));

$conversation->addMessage(
    new ChatInput('Get one word for each previous number.'),
);

$final = $conversation->runUntilResponse(maxSteps: 4);

print_r($final->result->words);
```

Historical tool calls do not require their original PHP callable after a
conversation is restored. Only a new tool call must match the current tool set.
See [Restoring an executable
conversation](conversations.md#restoring-an-executable-conversation-from-json).

Executable variants are available in:

- [`test-tool-function-calling.php`](../test-tool-function-calling.php);
- [`examples/04-tool-calling-manual.php`](../examples/04-tool-calling-manual.php);
- [`examples/05-tool-calling-conversation.php`](../examples/05-tool-calling-conversation.php).

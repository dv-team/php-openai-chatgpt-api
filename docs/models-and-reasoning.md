# Models and Reasoning

Model presets represent cost and capability categories:

| Preset | OpenAI model |
| --- | --- |
| `LLMLargeNoReasoning`, `LLMLargeReasoning` | `gpt-5.6-sol` |
| `LLMMediumNoReasoning`, `LLMMediumReasoning` | `gpt-5.6-terra` |
| `LLMSmallNoReasoning`, `LLMSmallReasoning` | `gpt-5.6-terra` |
| `LLMNanoNoReasoning` | `gpt-5.6-luna` |

Medium and Small intentionally resolve to the same Terra model. Their names
remain separate cost-category abstractions. Sol has its own Large category.

No-reasoning presets explicitly send `reasoning.effort: none`. Reasoning
presets accept `none`, `low`, `medium`, `high`, `xhigh`, and `max` through the
`ReasoningEffort` enum.

The examples assume that `$chat` is initialized as described in
[Getting Started](getting-started.md).

## Select a preset

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMNanoNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMLargeNoReasoning;

$response = $chat->chat(
    context: [new ChatInput('Explain traits in PHP.')],
    model: new LLMSmallNoReasoning(),
    maxTokens: 512,
);
```

## Configure reasoning effort

```php
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMLargeReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;

$small = $chat->chat(
    context: [new ChatInput('Briefly explain how traits work in PHP.')],
    model: new LLMSmallReasoning(ReasoningEffort::Medium),
);

$large = $chat->chat(
    context: [
        new ChatInput('Design a robust error-handling strategy for a REST API.'),
    ],
    model: new LLMLargeReasoning(ReasoningEffort::High),
);

$custom = $chat->chat(
    context: [new ChatInput('Provide a bullet summary.')],
    model: new LLMCustomModel(
        model: 'gpt-5.6-sol',
        effort: ReasoningEffort::Low,
    ),
);

echo $small->firstChoice()->result, "\n";
```

## Model capability flags

Every `ChatModelName` announces support for:

```php
$model->supportsTemperature();
$model->supportsTopP();
$model->supportsMaxTokens();
```

`ChatGPT::chat()` sends `temperature`, `top_p`, and `max_output_tokens` only
when the selected model reports support. Persisted conversations store these
capability flags with the effective model configuration so restored requests
retain the same behavior.

# Text to Speech

`ChatGPT::textToSpeech()` returns raw audio bytes that can be written to a file
or streamed by the application.

The example assumes that `$chat` is initialized as described in
[Getting Started](getting-started.md).

```php
use DvTeam\ChatGPT\PredefinedModels\TextToSpeech\GPT4oMiniTextToSpeech;

$audio = $chat->textToSpeech(
    text: 'Hello, world!',
    voice: 'alloy',
    speed: 1.0,
    instructions: 'Sound quietly excited.',
    model: new GPT4oMiniTextToSpeech(),
    format: 'wav',
);

file_put_contents('/tmp/example.wav', $audio);
```

The return value is not JSON or a response object. It is the encoded audio
payload in the requested format.

An executable variant is available in
[`examples/07-text-to-speech.php`](../examples/07-text-to-speech.php).

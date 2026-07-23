# Attachments

`ChatInput` can append attachment content blocks to a Responses API message.
The built-in `ChatImageUrl` supports HTTP URLs and data URLs.

The examples assume that `$chat` is initialized as described in
[Getting Started](getting-started.md).

## Images by URL or inline data

```php
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

$response = $chat->chat([
    new ChatInput(
        content: 'Describe this image.',
        attachment: new ChatImageUrl('https://example.com/cat.jpg'),
    ),
]);
```

To send a local image, encode it as a data URL:

```php
$imageContents = file_get_contents('cat.jpg');
if($imageContents === false) {
    throw new RuntimeException('Could not read cat.jpg.');
}

$base64 = base64_encode($imageContents);

$response = $chat->chat([
    new ChatInput(
        content: 'Describe this image.',
        attachment: new ChatImageUrl("data:image/jpeg;base64,{$base64}"),
    ),
]);
```

## Custom attachments

Every attachment implements
`DvTeam\ChatGPT\Messages\ChatAttachment::toInputContentParts()`. To preserve
the attachment in a serialized conversation, it must also implement
`DvTeam\ChatGPT\Common\ContextSerializable` and provide a stable serialized
`type`.

```php
use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Messages\ChatAttachment;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

final class CustomImageUrlAttachment implements ChatAttachment, ContextSerializable {
    public function __construct(public string $url) {}

    public function toInputContentParts(): array {
        return [
            (object) [
                'type' => 'input_image',
                'image_url' => $this->url,
            ],
        ];
    }

    public function contextSerialize(): array {
        return [
            'type' => 'custom_image_url',
            'url' => $this->url,
        ];
    }

    public static function contextUnserialize(array|object $data): self {
        $data = is_object($data) ? (array) $data : $data;

        return new self((string) ($data['url'] ?? ''));
    }
}
```

Register the decoder before restoring any context that contains this type:

```php
ChatInput::registerAttachmentType(
    'custom_image_url',
    [CustomImageUrlAttachment::class, 'contextUnserialize'],
);
```

`ChatImageUrl` is registered by default. Unknown attachment types cause
deserialization to fail rather than silently discarding content.

See [Conversations, Persistence, and Prompt Caching](conversations.md#attachments)
for the persistence lifecycle.

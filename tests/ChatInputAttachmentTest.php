<?php

declare(strict_types=1);

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Messages\ChatAttachment;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChatInputAttachmentTest extends TestCase {
	public function testCustomAttachmentIsUsedForApiInputAndCanBeDecoded(): void {
		ChatInput::registerAttachmentType('dummy_attachment', [DummyAttachment::class, 'contextUnserialize']);

		$input = new ChatInput(
			content: 'Hello',
			role: 'user',
			attachment: new DummyAttachment('https://example.com/a.png')
		);

		$apiPayload = $input->jsonSerialize();
		$this->assertSame('message', $apiPayload[0]->type ?? null);
		$this->assertSame('user', $apiPayload[0]->role ?? null);
		$this->assertSame('input_text', $apiPayload[0]->content[0]->type ?? null);
		$this->assertSame('Hello', $apiPayload[0]->content[0]->text ?? null);
		$this->assertSame('input_image', $apiPayload[0]->content[1]->type ?? null);
		$this->assertSame('https://example.com/a.png', $apiPayload[0]->content[1]->image_url ?? null);

		$serialized = $input->contextSerialize();
		$rehydrated = ChatInput::contextUnserialize($serialized);

		$this->assertInstanceOf(DummyAttachment::class, $rehydrated->attachment);
		$this->assertSame('https://example.com/a.png', $rehydrated->attachment->url);
	}
}

final class DummyAttachment implements ChatAttachment, ContextSerializable {
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
			'type' => 'dummy_attachment',
			'url' => $this->url,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		if(($data['type'] ?? null) !== 'dummy_attachment' || !is_string($data['url'] ?? null)) {
			throw new InvalidArgumentException('Invalid dummy_attachment payload.');
		}

		return new self($data['url']);
	}
}


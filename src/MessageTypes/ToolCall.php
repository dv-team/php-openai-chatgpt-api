<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\ContextSerializable;
use DvTeam\ChatGPT\Common\JSON;
use InvalidArgumentException;

/**
 * Describes a tool call for the message context that the LLM wanted to execute.
 */
class ToolCall implements ContextSerializable {
	/**
	 * @param string $id A unique identifier to connect the tool call with the result.
	 * @param string $name The name of the tool (function).
	 * @param array<string, mixed>|object $arguments The arguments for the tool call.
	 * @param string $type The type of the tool (function).
	 */
	public function __construct(
		public string $id,
		public string $name,
		public array|object $arguments,
		public string $type = 'function',
	) {}

	/**
	 * Maps an assistant tool-call message to the Responses API input schema.
	 *
	 * @return object{type: 'function_call', call_id: string, name: string, arguments: string}
	 */
	public function jsonSerialize(): object {
		return (object) [
			'type' => 'function_call',
			'call_id' => $this->id,
			'name' => $this->name,
			'arguments' => JSON::stringify($this->arguments),
		];
	}

	/**
	 * @return array{type: string, id: string, name?: string, arguments?: array<string, mixed>|array<int, mixed>, tool_type?: string}
	 */
	public function contextSerialize(): array {
		$arguments = $this->arguments;
		if(is_object($arguments)) {
			// Convert to plain arrays for stable transport (e.g. json_decode() roundtrips).
			$arguments = json_decode(JSON::stringify($arguments), true, 512, JSON_THROW_ON_ERROR);
		}

		if(!is_array($arguments)) {
			throw new InvalidArgumentException('Invalid tool_call arguments payload.');
		}

		return [
			'type' => 'tool_call',
			'id' => $this->id,
			'name' => $this->name,
			'arguments' => $arguments,
			'tool_type' => $this->type,
		];
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		if(($data['type'] ?? null) !== 'tool_call') {
			throw new InvalidArgumentException('Invalid tool_call payload.');
		}

		$id = $data['id'] ?? null;
		$name = $data['name'] ?? null;
		$arguments = $data['arguments'] ?? [];

		if(!is_string($id) || !is_string($name)) {
			throw new InvalidArgumentException('Invalid tool_call payload.');
		}

		$toolType = is_string($data['tool_type'] ?? null) ? (string) $data['tool_type'] : 'function';

		// Backwards-compat: older payloads may include role; ignore it (tool calls are not messages).
		if(array_key_exists('role', $data) && !is_string($data['role'])) {
			throw new InvalidArgumentException('Invalid tool_call payload.');
		}

		if(is_object($arguments)) {
			$arguments = json_decode(JSON::stringify($arguments), true, 512, JSON_THROW_ON_ERROR);
		} elseif(is_string($arguments)) {
			$arguments = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
		}

		if(!is_array($arguments)) {
			throw new InvalidArgumentException('Invalid tool_call arguments payload.');
		}

		return new self(
			id: $id,
			name: $name,
			arguments: $arguments,
			type: $toolType,
		);
	}

	public function __serialize(): array {
		return $this->contextSerialize();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function __unserialize(array $data): void {
		$obj = self::contextUnserialize($data);

		$this->id = $obj->id;
		$this->name = $obj->name;
		$this->arguments = $obj->arguments;
		$this->type = $obj->type;
	}
}

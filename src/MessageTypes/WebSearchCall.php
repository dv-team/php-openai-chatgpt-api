<?php

namespace DvTeam\ChatGPT\MessageTypes;

use DvTeam\ChatGPT\Common\JSON;
use InvalidArgumentException;

/**
 * Special helper class for web searches in chat context.
 *
 * This class extends ToolCall and creates a function call
 * with the name 'web_search' and appropriate arguments (query,
 * user_location?, model?, effort?). This allows it to be passed
 * directly into the message context for ChatGPT::chat().
 */
class WebSearchCall extends ToolCall {
	/**
	 * Komfort: Erzeugt eine WebSearchCall-Instanz mit generierter ID.
	 *
	 * @param string $id
	 * @param string $query
	 * @param array<string, mixed>|null $userLocation
	 * @param string|null $model
	 * @param string|null $effort
	 */
	public static function mk(
		string $id,
		string $query,
		?array $userLocation = null,
		?string $model = null,
		?string $effort = null,
	): self {
		return new self($id, $query, $userLocation, $model, $effort);
	}

	/**
 	 * @param string $id Eindeutige ID, um Call und Result zu verbinden
 	 * @param string $query Suchbegriff
 	 * @param array<string, mixed>|null $userLocation optionale Nutzer-Lokalisierung
	 * @param string|null $model optionales Modell (z. B. "gpt-5.6-sol")
	 * @param string|null $effort optionales Reasoning-Effort ("none"|"low"|"medium"|"high"|"xhigh"|"max")
 	 */
	public function __construct(
		string $id,
		string $query,
		?array $userLocation = null,
		?string $model = null,
		?string $effort = null,
	) {
		$args = ['query' => $query];
		if ($userLocation !== null) {
			$args['user_location'] = $userLocation;
		}
		if ($model !== null) {
			$args['model'] = $model;
		}
		if ($effort !== null) {
			$args['effort'] = $effort;
		}

		parent::__construct(
			id: $id,
			name: 'web_search',
			arguments: $args,
			type: 'function',
		);
	}

	/**
	 * @return array{type: 'web_search_call', id: string, query: string, user_location?: array<string, mixed>, model?: string, effort?: string}
	 */
	public function contextSerialize(): array {
		$args = $this->arguments;
		if(!is_array($args)) {
			throw new InvalidArgumentException('Invalid web_search_call arguments payload.');
		}

		$query = $args['query'] ?? null;
		if(!is_string($query)) {
			throw new InvalidArgumentException('Invalid web_search_call query payload.');
		}

		$data = [
			'type' => 'web_search_call',
			'id' => $this->id,
			'query' => $query,
		];

		if(isset($args['user_location']) && is_array($args['user_location'])) {
			$data['user_location'] = $args['user_location'];
		}
		if(isset($args['model']) && is_string($args['model'])) {
			$data['model'] = $args['model'];
		}
		if(isset($args['effort']) && is_string($args['effort'])) {
			$data['effort'] = $args['effort'];
		}

		return $data;
	}

	public static function contextUnserialize(array|object $data): self {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$type = $data['type'] ?? null;

		if($type !== 'web_search_call') {
			throw new InvalidArgumentException('Invalid web_search_call payload.');
		}

		$id = $data['id'] ?? null;
		$query = $data['query'] ?? null;
		$userLocation = $data['user_location'] ?? null;
		$model = $data['model'] ?? null;
		$effort = $data['effort'] ?? null;

		if(!is_string($id) || !is_string($query)) {
			throw new InvalidArgumentException('Invalid web_search_call payload.');
		}

		if(is_object($userLocation)) {
			$userLocation = json_decode(JSON::stringify($userLocation), true, 512, JSON_THROW_ON_ERROR);
		}

		return new self(
			id: $id,
			query: $query,
			userLocation: is_array($userLocation) ? $userLocation : null,
			model: is_string($model) ? $model : null,
			effort: is_string($effort) ? $effort : null,
		);
	}
}

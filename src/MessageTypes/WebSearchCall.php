<?php

namespace DvTeam\ChatGPT\MessageTypes;

/**
 * Spezielle Hilfsklasse für Web-Suchen im Chat-Kontext.
 *
 * Diese Klasse erweitert ToolCall und erzeugt einen Funktionsaufruf
 * mit dem Namen 'web_search' sowie passenden Argumenten (query,
 * user_location?, model?, effort?). Dadurch kann sie direkt in den
 * Nachrichten-Context für ChatGPT::chat() übergeben werden.
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
 	 * @param string|null $model optionales Modell (z. B. "gpt-5.1")
 	 * @param string|null $effort optionales Reasoning-Effort ("low"|"medium"|"high")
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
			role: 'assistant',
		);
	}
}


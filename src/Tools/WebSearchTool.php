<?php

namespace DvTeam\ChatGPT\Tools;

use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Common\ChatModelName;

/**
 * Callable web-search tool for GPTConversation.
 *
 * @phpstan-type TUserLocation array{type: string, city?: string, region?: string, country?: string, timezone?: string}
 */
final class WebSearchTool {
	/**
	 * @param TUserLocation|null $userLocation
	 */
	public function __construct(
		private readonly ChatGPT $chat,
		private readonly ?array $userLocation = null,
		private readonly ?ChatModelName $model = null,
	) {}

	/**
	 * @return array{query: string, answer: string, model: string}
	 */
	#[GPTCallableDescriptor(
		name: 'web_search',
		description: 'Searches the current public web for product facts, specifications, support information, and common buyer concerns.'
	)]
	public function __invoke(
		#[GPTParameterDescriptor(['description' => 'A focused search query containing the exact product and the information needed.'])]
		string $query,
	): array {
		$response = $this->chat->webSearch(
			query: $query,
			userLocation: $this->userLocation,
			model: $this->model,
		);

		return [
			'query' => $query,
			'answer' => $response->getFirstText(),
			'model' => $response->model,
		];
	}
}

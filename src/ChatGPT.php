<?php

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\ChatEnquiry;
use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\JsonSchemaValidator;
use DvTeam\ChatGPT\Common\MessageInterceptorInterface;
use DvTeam\ChatGPT\Exceptions\InvalidResponseException;
use DvTeam\ChatGPT\Exceptions\NoResponseFromAPI;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\Http\HttpPostInterface;
use DvTeam\ChatGPT\Messages\ChatImageUrl;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\Response\ChatFuncCallResult;
use DvTeam\ChatGPT\Response\ChatResponse;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use DvTeam\ChatGPT\Response\WebSearchResponse;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * @phpstan-import-type TUserLocation from WebSearchResponse
 */
class ChatGPT {
	private JsonSchemaValidator $jsonSchemaValidator;
	private MessageInterceptorInterface $messageInterceptor;

	public function __construct(
		private readonly OpenAIToken $token,
		private readonly HttpPostInterface $httpPostClient,
		?JsonSchemaValidator $jsonSchemaValidator = null,
		?MessageInterceptorInterface $messageInterceptor = null,
	) {
		$this->messageInterceptor = $messageInterceptor ?? new class implements MessageInterceptorInterface {
			public function invoke(ChatEnquiry $enquiry, $fn): string {
				return $fn($enquiry);
			}
		};

		$this->jsonSchemaValidator = $jsonSchemaValidator ?? new class implements JsonSchemaValidator {
			public function validate(mixed $data, array $schema): bool {
				$validator = new Validator();

				return ($validator)->validate($data, JSON::stringify($schema))->isValid();
			}
		};
	}

	/**
	 * @param ChatMessage[] $context
	 * @param GPTFunctions|null $functions
	 * @param JsonSchemaResponseFormat|null $responseFormat
	 * @param ChatModelName|null $model
	 * @param int $maxTokens
	 * @param null|float $temperature The temperature as described in the [here](https://community.openai.com/t/cheat-sheet-mastering-temperature-and-top-p-in-chatgpt-api/172683).
	 * @return ChatResponse
	 */
	public function chat(
		array $context,
		null|GPTFunctions $functions = null,
		null|JsonSchemaResponseFormat $responseFormat = null,
		null|ChatModelName $model = null,
		int $maxTokens = 2500,
		?float $temperature = null,
		?float $topP = null,
	): ChatResponse {
		$model ??= new LLMMediumNoReasoning();

		$responseRaw = $this->internalChatEnquiry(
			new ChatEnquiry(
				inputs: $context,
				model: $model,
				functions: $functions?->jsonSerialize() ?? [],
				responseFormat: $responseFormat?->jsonSerialize(),
				maxTokens: $maxTokens,
				temperature: $temperature,
				topP: $topP,
			)
		);

		/** @var object{choices?: array<object{message?: object{content?: string, tool_calls?: object{id: string, function: object{name: string, arguments: string}}[]}, finish_reason: string}>} $responseData */
		$responseData = JSON::parse($responseRaw);

		$choices = $responseData->choices ?? [];
		if(!count($choices)) {
			throw new NoResponseFromAPI('Invalid or incomplete response from OpenAI.');
		}

		$resultChoices = [];
		foreach($choices as $choice) {
			if($choice->finish_reason !== 'stop' && $choice->finish_reason !== 'tool_calls') {
				continue;
			}

			$message = $choice->message->content ?? null;
			if($message !== null && $responseFormat instanceof JsonSchemaResponseFormat) {
				$message = JSON::parse($message);
				/** @var array{json_schema: mixed[]} $jsonSchema */
				$jsonSchema = $responseFormat->jsonSerialize();
				$result = $this->jsonSchemaValidator->validate($message, $jsonSchema['json_schema']);
				if(!$result) {
					throw new InvalidResponseException('Invalid response from OpenAI.');
				}
			}

			$toolResults = [];
			foreach($choice->message->tool_calls ?? [] as $toolCall) {
				$id = $toolCall->id;
				$fnName = $toolCall->function->name;
				$jsonArgs = $toolCall->function->arguments;

				/** @var object $arguments */
				$arguments = JSON::parse($jsonArgs);

				if(!is_object($arguments)) {
					throw new InvalidResponseException('Invalid or incomplete response from OpenAI.');
				}

				$toolResults[] = new ChatFuncCallResult(
					id: $id,
					functionName: $fnName,
					arguments: $arguments,
					toolCallMessage: new ToolCall(
						id: $id,
						name: $fnName,
						arguments: $arguments
					)
				);
			}

			$resultChoices[] = new ChatResponseChoice(
				result: $message,
				tools: $toolResults
			);
		}

		return new ChatResponse(choices: $resultChoices);
	}

	/**
	 * @param string $query
	 * @param TUserLocation|null $userLocation
	 * @param ChatModelName|null $model Optional model (defaults like chat)
	 * @return WebSearchResponse
	 * @throws \JsonException
	 */
	public function webSearch(string $query, ?array $userLocation = null, ?ChatModelName $model = null): WebSearchResponse {
		$model ??= new LLMMediumNoReasoning();
		$tool = [
			'type' => 'web_search',
		];

		if($userLocation !== null) {
			$tool['user_location'] = $userLocation;
		}

		$body = [
			'model' => (string) $model,
			'tools' => [$tool],
			'input' => $query,
		];

		// Derive reasoning effort from model if available; default to 'low'
		$reasoningEffort = $this->getReasoningEffort($model);
		if($reasoningEffort !== null) {
			$body['reasoning']['effort'] = $reasoningEffort;
		}

		$responseJson = $this->httpPostClient->post('https://api.openai.com/v1/responses', $body, [
			'Authorization' => "Bearer {$this->token}",
			'Content-Type' => 'application/json',
		]);

		/** @var object{output: object{type: string, status: string}[]} $response */
		$response = JSON::parse($responseJson);

		foreach($response->output as $output) {
			if($output->type === 'message' && $output->status === 'completed') {
				return new WebSearchResponse(
					output: $output,
					structure: $response,
					query: $query,
					userLocation: $userLocation,
					model: (string) $model,
					effort: $reasoningEffort,
				);
			}
		}

		throw new RuntimeException('Invalid response from OpenAI.');
	}

	/**
	 * @param ChatEnquiry $enquiry
	 * @return string
	 */
	private function internalChatEnquiry(ChatEnquiry $enquiry): string {
		return $this->messageInterceptor->invoke($enquiry, function(ChatEnquiry $enquiry) {
			$cInputs = [];
			foreach($enquiry->inputs as $input) {
				if($input instanceof ChatInput) {
					if($input->attachment instanceof ChatImageUrl) {
						$cInputs[] = [
							'role' => $input->role,
							'content' => [[
								'type' => 'text',
								'text' => $input->content,
							], [
								'type' => 'image_url',
								'image_url' => [
									'url' => $input->attachment->url,
								],
							]],
						];
					} elseif($input->attachment === null) {
						$cInputs[] = [
							'role' => $input->role,
							'content' => $input->content,
						];
					} else {
						throw new RuntimeException('Invalid parameter');
					}
				} elseif($input instanceof ToolCall) {
					$cInputs[] = [
						'role' => $input->role,
						'tool_calls' => [[
							'id' => $input->id,
							'type' => $input->type,
							'function' => [
								'name' => $input->name,
								'arguments' => JSON::stringify($input->arguments),
							],
						]],
					];
				} elseif($input instanceof ToolResult) {
					$cInputs[] = [
						'role' => $input->role,
						'tool_call_id' => $input->toolCallId,
						'name' => $input->name,
						'content' => JSON::stringify($input->content),
					];
				} else {
					throw new RuntimeException('Invalid parameter');
				}
			}

			$uri = 'https://api.openai.com/v1/chat/completions';
			$headers = ['Authorization' => "Bearer {$this->token}", 'Content-Type' => 'application/json'];
			$body = [
				'model' => (string) $enquiry->model,
				'messages' => $cInputs,
			];

			// For newer reasoning-capable models, include the configured effort if provided
			$reasoningEffort = $this->getReasoningEffort($enquiry->model);
			if($reasoningEffort !== null) {
				$body['reasoning']['effort'] = $reasoningEffort;
			}

			if($enquiry->responseFormat !== null) {
				$body['response_format'] = $enquiry->responseFormat;
			}

			if(str_starts_with($enquiry->model, 'gpt-5')) {
				//$body['max_completion'] = $enquiry->maxTokens;
			} else {
				$body['max_tokens'] = $enquiry->maxTokens;
			}

			if($enquiry->temperature !== null) {
				$body['temperature'] = $enquiry->temperature;
			}

			if($enquiry->topP !== null) {
				$body['top_p'] = $enquiry->topP;
			}

			if(count($enquiry->functions)) {
				$tools = [];

				foreach($enquiry->functions as $function) {
					$tools[] = [
						'type' => 'function',
						'function' => $function,
					];
				}

				$body['tools'] = $tools;
				$body['tool_choice'] = 'auto';
			}

			printf("REQUEST: %s\n", JSON::stringify($body));

			return $this->httpPostClient->post($uri, $body, $headers); // @phpstan-ignore-line
		});
	}

	private function getReasoningEffort(ChatModelName $model): ?string {
		if(str_starts_with((string) $model, 'gpt-5')) {
			if($model instanceof LLMSmallReasoning) {
				return $model->effort->value;
			}

			if($model instanceof LLMMediumReasoning) {
				return $model->effort->value;
			}

			if($model instanceof LLMCustomModel) {
				return $model->effort->value ?? null;
			}
		}
		return null;
	}
}

<?php

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\ChatEnquiry;
use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\JsonSchemaValidatorInterface;
use DvTeam\ChatGPT\Common\MessageInterceptorInterface;
use DvTeam\ChatGPT\Exceptions\InvalidResponseException;
use DvTeam\ChatGPT\Exceptions\NoResponseFromAPI;
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTObjectProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\Http\HttpPostInterface;
use DvTeam\ChatGPT\Http\LLMNetworkException;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\PredefinedModels\LLMCustomModel;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumReasoning;
use DvTeam\ChatGPT\PredefinedModels\LLMSmallReasoning;
use DvTeam\ChatGPT\PredefinedModels\TextToSpeech\GPTMiniTextToSpeech;
use DvTeam\ChatGPT\PredefinedModels\TextToSpeech\TextToSpeechModel;
use DvTeam\ChatGPT\Response\ChatFuncCallResult;
use DvTeam\ChatGPT\Response\ChatResponse;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use DvTeam\ChatGPT\Response\WebSearchResponse;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * @phpstan-import-type TUserLocation from WebSearchResponse
 * @phpstan-import-type TFunction from GPTFunction
 *
 * @phpstan-type TRequestContentItem object{
 *     type: string,
 *     text?: string
 * }
 *
 * @phpstan-type TRequestMessageInput object{
 *     role: string,
 *     content: array<int, TRequestContentItem>,
 *     type?: string,
 *     call_id?: string,
 *     output?: string
 * }
 *
 * @phpstan-type TRequestFormatSchema object{
 *     type: string,
 *     properties?: object,
 *     required?: array<int, string>,
 *     additionalProperties?: bool
 * }
 *
 * @phpstan-type TRequestTextFormat object{
 *     type: string,
 *     name?: string,
 *     schema?: TRequestFormatSchema,
 *     strict?: bool
 * }
 *
 * @phpstan-type TRequestTextConfig object{
 *     format?: TRequestTextFormat,
 *     max_output_tokens?: int
 * }
 *
 * @phpstan-type TRequestData object{
 *     model: string,
 *     input: array<int, TRequestMessageInput>,
 *     text?: TRequestTextConfig,
 *     tools?: object{name?: string, type?: string}[],
 *     tool_choice?: string
 * }
 *
 * @phpstan-type TResponseError object{
 *     message?: string,
 *     type?: string,
 *     param?: string|null,
 *     code?: string|null
 * }
 *
 * @phpstan-type TResponseContent object{
 *     type?: string,
 *     text?: string|object{value?: string},
 *     annotations?: mixed[],
 *     logprobs?: mixed[]
 * }
 *
 * @phpstan-type TResponseFunction object{
 *     name?: string,
 *     arguments?: string
 * }
 *
 * @phpstan-type TResponseToolCall object{
 *     id?: string,
 *     type?: string,
 *     function?: TResponseFunction
 * }
 *
 * @phpstan-type TResponseOutputItem object{
 *     id?: string,
 *     type?: string,
 *     status?: string,
 *     content?: array<int, TResponseContent>|string,
 *     text?: string|object{value?: string},
 *     role?: string,
 *     tool_calls?: array<int, TResponseToolCall>,
 *     name?: string,
 *     arguments?: string,
 *     function?: TResponseFunction,
 *     call_id?: string
 * }
 *
 * @phpstan-type TResponseUsage object{
 *     input_tokens?: int,
 *     input_tokens_details?: object{cached_tokens?: int},
 *     output_tokens?: int,
 *     output_tokens_details?: object{reasoning_tokens?: int},
 *     total_tokens?: int
 * }
 *
 * @phpstan-type TResponseBilling object{payer?: string}
 *
 * @phpstan-type TResponseReasoning object{
 *     effort?: string,
 *     summary?: string|null
 * }
 *
 * @phpstan-type TResponseTextFormatSchema object
 *
 * @phpstan-type TResponseTextFormat object{
 *     type?: string,
 *     description?: string|null,
 *     name?: string,
 *     schema?: TResponseTextFormatSchema,
 *     strict?: bool
 * }
 *
 * @phpstan-type TResponseText object{
 *     format?: TResponseTextFormat,
 *     verbosity?: string
 * }
 *
 * @phpstan-type TResponseMetadata object
 *
 * @phpstan-type TTool object{
 *     name?: string,
 *     type?: string
 * }
 *
 * @phpstan-type TResponseData object{
 *     id?: string,
 *     object?: string,
 *     created_at?: int,
 *     status?: string,
 *     background?: bool,
 *     billing?: TResponseBilling,
 *     completed_at?: int|null,
 *     error?: TResponseError|null,
 *     incomplete_details?: mixed,
 *     instructions?: string|null,
 *     max_output_tokens?: int|null,
 *     max_tool_calls?: int|null,
 *     model?: string,
 *     output?: array<int, TResponseOutputItem>,
 *     output_text?: string|array<int, string>,
 *     parallel_tool_calls?: bool,
 *     previous_response_id?: string|null,
 *     prompt_cache_key?: string|null,
 *     prompt_cache_retention?: string|null,
 *     reasoning?: TResponseReasoning,
 *     safety_identifier?: string|null,
 *     service_tier?: string,
 *     store?: bool,
 *     temperature?: float|int,
 *     text?: TResponseText,
 *     tool_choice?: string,
 *     tools?: TTool[],
 *     top_logprobs?: int,
 *     top_p?: float|int,
 *     truncation?: string,
 *     usage?: TResponseUsage,
 *     user?: string|null,
 *     metadata?: TResponseMetadata
 * }
 */
class ChatGPT {
	private JsonSchemaValidatorInterface $jsonSchemaValidator;
	private MessageInterceptorInterface $messageInterceptor;

	public function __construct(
		private readonly OpenAIToken $token,
		private readonly HttpPostInterface $httpPostClient,
		?JsonSchemaValidatorInterface $jsonSchemaValidator = null,
		?MessageInterceptorInterface $messageInterceptor = null,
	) {
		$this->messageInterceptor = $messageInterceptor ?? new class implements MessageInterceptorInterface {
			public function invoke(ChatEnquiry $enquiry, callable $next): string {
				return $next($enquiry);
			}
		};

		$this->jsonSchemaValidator = $jsonSchemaValidator ?? new class implements JsonSchemaValidatorInterface {
			public function validate(mixed $data, array $schema): bool {
				$validator = new Validator();

				try {
					return ($validator)->validate($data, JSON::stringify($schema))->isValid();
				} catch (\Throwable) {
					return false;
				}
			}
		};
	}

	/**
	 * @param ChatMessage[] $context
	 * @param callable[] $callableTools
	 * @param array<string, callable> $callableMap
	 */
	public function newConversation(
		array $context = [],
		array $callableTools = [],
		array $callableMap = [],
		?JsonSchemaResponseFormat $responseFormat = null,
		?ChatModelName $model = null,
		int $maxTokens = 2500,
		?float $temperature = null,
		?float $topP = null,
	): GPTConversation {
		return new GPTConversation(
			chat: $this,
			context: $context,
			callableTools: $callableTools,
			callableMap: $callableMap,
			functionsSpec: null,
			responseFormat: $responseFormat,
			model: $model,
			maxTokens: $maxTokens,
			temperature: $temperature,
			topP: $topP,
		);
	}

	/**
	 * @param ChatMessage[] $context
	 * @param GPTFunctions|null $functions
	 * @param JsonSchemaResponseFormat|null $responseFormat
	 * @param ChatModelName|null $model
	 * @param int $maxTokens
	 * @param null|float $temperature The temperature as described in the [here](https://community.openai.com/t/cheat-sheet-mastering-temperature-and-top-p-in-chatgpt-api/172683).
	 * @return ChatResponse<object>
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

		/** @var TFunction[] $functionPayload */
		$functionPayload = $functions?->jsonSerialize() ?? [];

		$responseRaw = $this->internalChatEnquiry(
			new ChatEnquiry(
				context: $context,
				model: $model,
				functions: $functionPayload,
				responseFormat: $responseFormat?->jsonSerialize(),
				maxTokens: $maxTokens,
				temperature: $temperature,
				topP: $topP,
			)
		);

		/** @var TResponseData|string $responseData */
		$responseData = JSON::parse($responseRaw);

		if(is_string($responseData)) {
			throw new InvalidResponseException($responseData);
		}

		if($responseData->error ?? null) {
			throw new InvalidResponseException($responseData->error->message ?? 'Unknown error');
		}

		$output = $responseData->output ?? [];
		if(!count($output) && !isset($responseData->output_text)) {
			throw new NoResponseFromAPI('Invalid or incomplete response from OpenAI.');
		}

		$messageParts = [];
		$toolResults = [];

		foreach($output as $item) {
			$type = $item->type ?? null;

			if($type === 'message') {
				$messageParts = array_merge($messageParts, $this->extractTextFromMessageOutput($item));

				foreach($item->tool_calls ?? [] as $toolCall) {
					$toolResults[] = $this->mapToolCallToResult($toolCall);
				}

				continue;
			}

			if($type === 'function_call' || $type === 'tool_call') {
				$toolResults[] = $this->mapToolCallToResult($item);
				continue;
			}

			if($type === 'output_text' && isset($item->text)) {
				$text = $this->normalizeTextValue($item->text);
				if($text !== null) {
					$messageParts[] = $text;
				}
			}
		}

		// Fallback: some clients expose aggregated output_text on the root object
		if(!count($messageParts) && isset($responseData->output_text)) {
			if(is_array($responseData->output_text)) {
				$messageParts = array_map(fn($t) => (string) $t, $responseData->output_text);
			} elseif(is_string($responseData->output_text)) {
				$messageParts = [$responseData->output_text];
			}
		}

		/** @var string $message */
		$message = count($messageParts) ? trim(implode("\n", array_filter($messageParts, fn($part) => $part !== ''))) : null;

		if($message !== null && $responseFormat instanceof JsonSchemaResponseFormat) {
			/** @var object $message */
			$message = JSON::parse($message);
			/** @var array{json_schema: mixed[]} $jsonSchema */
			$jsonSchema = $responseFormat->jsonSerialize();
			$result = $this->jsonSchemaValidator->validate($message, $jsonSchema['json_schema']);
			if(!$result) {
				throw new InvalidResponseException('Invalid response from OpenAI.');
			}
		}

		/** @var string|object|null $message */
		if($message === null && !count($toolResults)) {
			throw new NoResponseFromAPI('Invalid or incomplete response from OpenAI.');
		}

		$choice = new ChatResponseChoice(
			isToolCall: (bool) count($toolResults),
			result: $message,
			textResult: is_string($message) ? $message : null,
			objResult: is_object($message) ? $message : null,
			tools: $toolResults
		);

		return new ChatResponse(
			choices: [$choice],
		);
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
		])->body;

		/** @var object{id: string, output: object{type: string, status: string}[]} $response */
		$response = JSON::parse($responseJson);

		foreach($response->output as $output) {
			if($output->type === 'message' && $output->status === 'completed') {
				return new WebSearchResponse(
					id: $response->id,
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
	 * @param string $text
	 * @param string $voice
	 * @param float $speed
	 * @param string|null $instructions
	 * @param TextToSpeechModel|null $model
	 * @param string $format
	 * @return string Binary audio data (e.g. WAV)
	 */
	public function textToSpeech(
		string $text,
		string $voice = 'alloy',
		float $speed = 1.0,
		?string $instructions = null,
		TextToSpeechModel|null $model = null,
		string $format = 'wav',
	): string {
		$model ??= new GPTMiniTextToSpeech();

		$body = [
			'model' => (string) $model,
			'input' => $text,
			'voice' => $voice,
			'format' => $format,
			'speed' => $speed,
		];

		if($instructions !== null && $instructions !== '') {
			$body['instructions'] = $instructions;
		}

		$response = $this->httpPostClient->post(
			'https://api.openai.com/v1/audio/speech',
			$body,
			[
				'Authorization' => "Bearer {$this->token}",
				'Content-Type' => 'application/json',
				'Accept' => "audio/{$format}",
			]
		)->body;

		/** @var array{error?: array{message?: string}}|null $decoded */
		$decoded = json_decode($response, true);
		if(json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['error'])) {
			$message = $decoded['error']['message'] ?? 'Unknown error';
			throw new RuntimeException("OpenAI TTS error: {$message}");
		}

		return $response;
	}

	/**
	 * Build a callable GPT function that performs an OpenAI web search and returns the first text plus metadata.
	 * If no defaults are provided, the LLM must supply user_location, model, and effort values.
	 *
	 * @param TUserLocation|null $defaultUserLocation
	 * @param ChatModelName|null $defaultModel
	 */
	public function buildWebSearchFunction(
		?array $defaultUserLocation = null,
		?ChatModelName $defaultModel = null,
	): GPTFunction {
		$properties = new GPTProperties(
			new GPTStringProperty(
				name: 'query',
				description: 'The search query.',
				required: true
			),
			new GPTObjectProperty(
				name: 'user_location',
				description: 'User location hints (exact|approximate, city, region, country, timezone). Required unless a default was supplied server-side.',
				properties: new GPTProperties(
					new GPTStringProperty('type', 'exact|approximate'),
					new GPTStringProperty('city', 'City name'),
					new GPTStringProperty('region', 'Region/state'),
					new GPTStringProperty('country', 'Country code (ISO 3166-1 alpha-2)'),
					new GPTStringProperty('timezone', 'IANA timezone')
				),
				required: $defaultUserLocation === null,
			),
			new GPTStringProperty(
				name: 'model',
				description: 'Optional model name for web search (if omitted, server defaults apply). `standard` translates to the largest model available (like `gpt-5.1`). `small` translates to something like `gpt-5.1-small`. `nano` translates to something like `gpt-5.1-nano`',
				enum: ['standard', 'mini', 'nano'],
				required: $defaultModel === null,
			),
		);

		return new GPTFunction(
			name: 'web_search',
			description: 'Search the web for information.',
			properties: $properties
		);
	}

	/**
	 * @param ChatEnquiry $enquiry
	 * @return string
	 */
	private function internalChatEnquiry(ChatEnquiry $enquiry): string {
		return $this->messageInterceptor->invoke($enquiry, function(ChatEnquiry $enquiry) {
			$cInputs = [];
			foreach($enquiry->context as $input) {
				if(!$input instanceof ChatMessage) {
					throw new RuntimeException('Every input must be an instance of ChatMessage.');
				}
				foreach($input->jsonSerialize() as $cInput) {
					$cInputs[] = $cInput;
				}
			}

			$uri = 'https://api.openai.com/v1/responses';
			$headers = ['Authorization' => "Bearer {$this->token}", 'Content-Type' => 'application/json'];
			$body = [
				'model' => (string) $enquiry->model,
				'input' => $cInputs,
			];

			// For newer reasoning-capable models, include the configured effort if provided
			$reasoningEffort = $this->getReasoningEffort($enquiry->model);
			if($reasoningEffort !== null) {
				$body['reasoning']['effort'] = $reasoningEffort;
			}

			if($enquiry->responseFormat !== null) {
				$body['text']['format'] = $this->mapResponseFormatForResponsesApi($enquiry->responseFormat);
			}

			if($enquiry->maxTokens !== null) {
				$body['max_output_tokens'] = $enquiry->maxTokens;
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
					$function['type'] = 'function';
					$tools[] = $function;
				}

				$body['tools'] = $tools;
				$body['tool_choice'] = 'auto';
			}

			try {
				return $this->httpPostClient->post($uri, $body, $headers)->body;
			} catch(LLMNetworkException $e) {
				throw $e;
			}
		});
	}

	/**
	 * Extract concatenated text content from a message output item.
	 *
	 * @return string[]
	 */
	private function extractTextFromMessageOutput(object $message): array {
		$content = $message->content ?? null;

		if(is_string($content)) {
			return [$content];
		}

		$textParts = [];
		if(is_array($content)) {
			foreach($content as $part) {
				$text = null;
				if(is_object($part)) {
					if(isset($part->text)) {
						$text = $this->normalizeTextValue($part->text);
					} elseif(isset($part->type) && $part->type === 'output_text' && isset($part->text)) {
						$text = $this->normalizeTextValue($part->text);
					}
				}

				if($text !== null) {
					$textParts[] = $text;
				}
			}
		}

		return $textParts;
	}

	private function normalizeTextValue(mixed $text): ?string {
		if(is_string($text)) {
			return $text;
		}

		if(is_object($text) && isset($text->value)) {
			return (string) $text->value;
		}

		return null;
	}

	/**
	 * Convert a Responses API tool call (or tool_call/tool message) to our ChatFuncCallResult.
	 */
	private function mapToolCallToResult(object $toolCall): ChatFuncCallResult {
		$fnName = $toolCall->function->name ?? $toolCall->name ?? null;
		$argumentsRaw = $toolCall->function->arguments ?? $toolCall->arguments ?? null;
		$id = $toolCall->call_id ?? $toolCall->id ?? null;

		if($fnName === null || $argumentsRaw === null || $id === null) {
			throw new InvalidResponseException('Invalid or incomplete response from OpenAI.');
		}

		$arguments = $this->normalizeToolArguments($argumentsRaw);

		return new ChatFuncCallResult(
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

	private function normalizeToolArguments(mixed $argumentsRaw): object {
		if(is_string($argumentsRaw)) {
			$arguments = JSON::parse($argumentsRaw);
		} elseif(is_object($argumentsRaw)) {
			$arguments = $argumentsRaw;
		} elseif(is_array($argumentsRaw)) {
			$arguments = JSON::parse(JSON::stringify($argumentsRaw));
		} else {
			throw new InvalidResponseException('Invalid or incomplete response from OpenAI.');
		}

		if(!is_object($arguments)) {
			throw new InvalidResponseException('Invalid or incomplete response from OpenAI.');
		}

		return $arguments;
	}

	/**
	 * Convert the library's responseFormat (legacy chat shape) to the Responses API shape.
	 *
	 * For JSON schema, the Responses API expects: ["type" => "json_schema", "name" => string, "schema" => object, "strict" => bool]
	 *
	 * @param array<string, mixed>|object $format
	 * @return array<string, mixed>
	 */
	private function mapResponseFormatForResponsesApi(array|object $format): array {
		if(is_object($format)) {
			$format = (array) $format;
		}

		if(($format['type'] ?? null) === 'json_schema') {
			$jsonSchema = $format['json_schema'] ?? [];
			if(is_object($jsonSchema)) {
				$jsonSchema = (array) $jsonSchema;
			}

			$name = $jsonSchema['name'] ?? 'Response';
			$schema = $this->enforceResponsesJsonSchema($jsonSchema['schema'] ?? []);
			$strict = $jsonSchema['strict'] ?? true;

			return [
				'type' => 'json_schema',
				'name' => $name,
				'schema' => $schema,
				'strict' => $strict,
			];
		}

		return $format;
	}

	/**
	 * OpenAI Responses API requires explicit required keys and additionalProperties on every object.
	 */
	private function enforceResponsesJsonSchema(mixed $schema): mixed {
		if(is_object($schema)) {
			$schema = (array) $schema;
		}

		if(!is_array($schema)) {
			return $schema;
		}

		$type = $schema['type'] ?? null;

		if($type === 'object') {
			$properties = $schema['properties'] ?? [];
			if(is_object($properties)) {
				$properties = (array) $properties;
			}

			if(is_array($properties)) {
				foreach($properties as $key => $propSchema) {
					$properties[$key] = $this->enforceResponsesJsonSchema($propSchema);
				}
				$schema['properties'] = $properties;

				if(!isset($schema['required'])) {
					$schema['required'] = array_keys($properties);
				}
			}

			if(!array_key_exists('additionalProperties', $schema)) {
				$schema['additionalProperties'] = false;
			}
		}

		if($type === 'array' && isset($schema['items'])) {
			$schema['items'] = $this->enforceResponsesJsonSchema($schema['items']);
		}

		return $schema;
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

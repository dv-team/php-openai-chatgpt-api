<?php

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Common\PersistedChatModel;
use DvTeam\ChatGPT\Common\PromptCacheOptions;
use DvTeam\ChatGPT\Common\ReasoningEffortProvider;
use DvTeam\ChatGPT\Exceptions\InvalidResponseException;
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTBooleanProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTIntegerProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ChatOutput;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;
use DvTeam\ChatGPT\Reflection\CallableInvoker;
use DvTeam\ChatGPT\Reflection\CallableNameNormalizer;
use DvTeam\ChatGPT\PredefinedModels\LLMMediumNoReasoning;
use DvTeam\ChatGPT\PredefinedModels\ReasoningEffort;
use DvTeam\ChatGPT\Response\ChatResponse;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

/**
 * The conversation class is responsible for managing interactions with ChatGPT.
 * Every step is managed by the conversation class, including sending messages,
 * receiving responses, and maintaining the conversation state.
 *
 * Every step is returned to the user and can be serialized to JSON. That way,
 * an ongoing conversation can be sent to the browser as a JSON object and resumed
 * later.
 *
 * The user can access and alter each message in the conversation history.
 *
 * This class uses {@see ChatGPT} as a low level client to interact with ChatGPT.
 */
class GPTConversation {
	/**
	 * @param ChatMessage[] $context
	 * @param callable[] $callableTools
	 * @param array<string, callable> $callableMap
	 */
	public function __construct(
		private readonly ChatGPT $chat,
		private array $context = [],
		private array $callableTools = [],
		private array $callableMap = [],
		private ?GPTFunctions $functionsSpec = null,
		private ?JsonSchemaResponseFormat $responseFormat = null,
		public ?ChatModelName $model = null,
		public int $maxTokens = 2500,
		public ?float $temperature = null,
		public ?float $topP = null,
		public ?string $promptCacheKey = null,
		public ?PromptCacheOptions $promptCacheOptions = null,
	) {
		$this->setTools($callableTools);
	}

	/**
	 * Execute one round against the API.
	 *
	 * - Calls ChatGPT exactly once unless $rerunOnToolUse is true.
	 * - Appends the assistant message (and any tool calls) to the context.
	 * - Auto-executes callable tools locally and appends ToolResult.
	 * - With $rerunOnToolUse, continues for at most eight API rounds.
	 *
	 * @return ChatResponseChoice<object>
	 */
	public function step(bool $rerunOnToolUse = false): ChatResponseChoice {
		if($rerunOnToolUse) {
			return $this->runUntilResponse();
		}

		return $this->stepOnce();
	}

	/**
	 * Continue through local tool calls until the model returns a user-facing response.
	 *
	 * @param callable(ToolCall, int): void|null $onToolCall Invoked after a requested tool was executed.
	 * @return ChatResponseChoice<object>
	 */
	public function runUntilResponse(int $maxSteps = 8, ?callable $onToolCall = null): ChatResponseChoice {
		if($maxSteps < 1) {
			throw new InvalidArgumentException('maxSteps must be at least 1.');
		}

		for($step = 1; $step <= $maxSteps; $step++) {
			$response = $this->stepOnce();

			if($onToolCall !== null) {
				foreach($response->tools as $tool) {
					$onToolCall($tool, $step);
				}
			}

			if(!$response->isToolCall) {
				return $response;
			}
		}

		throw new RuntimeException(sprintf(
			'Conversation did not produce a user-facing response within %d API steps.',
			$maxSteps
		));
	}

	/**
	 * @return ChatResponseChoice<object>
	 */
	private function stepOnce(): ChatResponseChoice {
		$this->rebuildFunctionsSpec();

		$response = $this->chat->chat(
			context: $this->context,
			functions: $this->functionsSpec,
			responseFormat: $this->responseFormat,
			model: $this->model,
			maxTokens: $this->maxTokens,
			temperature: $this->temperature,
			topP: $this->topP,
			promptCacheKey: $this->promptCacheKey,
			promptCacheOptions: $this->promptCacheOptions,
		);

		$response = $this->absorbResponse($response);

		return $response;
	}

	/**
	 * Returns a copy of the current conversation.
	 */
	public function split(): GPTConversation {
		return new self(
			chat: $this->chat,
			context: $this->context,
			callableTools: $this->callableTools,
			callableMap: $this->callableMap,
			functionsSpec: $this->functionsSpec,
			responseFormat: $this->responseFormat,
			model: $this->model,
			maxTokens: $this->maxTokens,
			temperature: $this->temperature,
			topP: $this->topP,
			promptCacheKey: $this->promptCacheKey,
			promptCacheOptions: $this->promptCacheOptions,
		);
	}

	/**
	 * Current conversation context.
	 *
	 * @return ChatMessage[]
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * Add a message (e.g., new user input) to the context.
	 */
	public function addMessage(ChatMessage $message): self {
		$this->context[] = $message;
		return $this;
	}

	/**
	 * Convenience: add a web_search tool call and its default arguments.
	 *
	 * @param string $query
	 * @param array<string, mixed>|null $userLocation
	 * @param string|null $model
	 * @param string|null $effort
	 * @return self
	 */
	public function addWebSearch(string $query, ?array $userLocation = null, ?string $model = null, ?string $effort = null): self {
		$toolId = uniqid('web_', true);
		$this->context[] = new ChatOutput(
			result: null,
			tools: [
				new WebSearchCall(
					id: $toolId,
					query: $query,
					userLocation: $userLocation,
					model: $model,
					effort: $effort,
				)
			],
		);
		return $this;
	}

	/**
	 * Register an additional callable tool.
	 */
	public function addTool(callable $callable): self {
		$this->validateCallable($callable);
		$this->callableTools[] = $callable;
		$this->rebuildFunctionsSpec();
		return $this;
	}

	/**
	 * Replace callable tools for subsequent steps.
	 *
	 * @param callable[] $callables
	 */
	public function setTools(array $callables): self {
		$this->callableTools = [];
		foreach($callables as $callable) {
			$this->addTool($callable);
		}

		return $this;
	}

	/**
	 * Replace the response format for subsequent steps.
	 */
	public function setResponseFormat(?JsonSchemaResponseFormat $format): self {
		$this->responseFormat = $format;
		return $this;
	}

	/**
	 * Serialize the context for transport (e.g., to a browser).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function serialize(): array {
		return array_map([$this, 'encodeMessage'], $this->context);
	}

	/**
	 * Serialize the complete resumable conversation configuration.
	 *
	 * Callable tools and the ChatGPT client are intentionally not included.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		$model = $this->model ?? new LLMMediumNoReasoning();
		$reasoningEffort = $model instanceof ReasoningEffortProvider
			? $model->reasoningEffort()
			: null;

		$responseFormat = null;
		if($this->responseFormat !== null) {
			$responseFormat = [
				'schema' => $this->responseFormat->schema,
				'strict' => $this->responseFormat->strict,
			];
		}

		$promptCache = null;
		if($this->promptCacheKey !== null || $this->promptCacheOptions !== null) {
			$promptCache = [
				'key' => $this->promptCacheKey,
				'options' => $this->promptCacheOptions?->jsonSerialize(),
			];
		}

		return [
			'version' => 1,
			'context' => ChatGPT::contextAsArray($this->context),
			'model' => [
				'name' => (string) $model,
				'supports_temperature' => $model->supportsTemperature(),
				'supports_top_p' => $model->supportsTopP(),
				'supports_max_tokens' => $model->supportsMaxTokens(),
				'reasoning_effort' => $reasoningEffort?->value,
			],
			'response_format' => $responseFormat,
			'max_tokens' => $this->maxTokens,
			'temperature' => $this->temperature,
			'top_p' => $this->topP,
			'prompt_cache' => $promptCache,
		];
	}

	public function toJson(): string {
		return JSON::stringify($this->toArray());
	}

	/**
	 * Restore a complete conversation session while injecting the currently available tools.
	 *
	 * @param array<string, mixed> $payload
	 * @param array<int, callable> $tools
	 */
	public static function fromArray(ChatGPT $chat, array $payload, array $tools = []): self {
		if(($payload['version'] ?? null) !== 1) {
			throw new InvalidArgumentException('Unsupported conversation session version.');
		}

		$contextPayload = $payload['context'] ?? null;
		if(!is_array($contextPayload)) {
			throw new InvalidArgumentException('Invalid conversation session context.');
		}

		foreach($contextPayload as $message) {
			if(!is_array($message) && !is_object($message)) {
				throw new InvalidArgumentException('Invalid message in conversation session context.');
			}
		}

		/** @var array<int, array<string, mixed>|object> $contextPayload */
		$decodedContext = ChatGPT::contextFromArray($contextPayload);
		$context = [];
		foreach($decodedContext as $message) {
			if(!$message instanceof ChatMessage) {
				throw new InvalidArgumentException('Conversation session context contains a non-message item.');
			}
			$context[] = $message;
		}

		$modelPayload = $payload['model'] ?? null;
		if(!is_array($modelPayload) && !is_object($modelPayload)) {
			throw new InvalidArgumentException('Invalid conversation session model.');
		}
		$model = self::decodeSessionModel($modelPayload);

		$responseFormat = self::decodeSessionResponseFormat($payload['response_format'] ?? null);

		$maxTokens = $payload['max_tokens'] ?? null;
		if(!is_int($maxTokens)) {
			throw new InvalidArgumentException('Invalid conversation session max_tokens.');
		}

		$temperature = self::decodeNullableFloat($payload['temperature'] ?? null, 'temperature');
		$topP = self::decodeNullableFloat($payload['top_p'] ?? null, 'top_p');

		$promptCacheKey = null;
		$promptCacheOptions = null;
		$promptCache = $payload['prompt_cache'] ?? null;
		if($promptCache !== null) {
			if(is_object($promptCache)) {
				$promptCache = (array) $promptCache;
			}
			if(!is_array($promptCache)) {
				throw new InvalidArgumentException('Invalid conversation session prompt_cache.');
			}

			$promptCacheKey = $promptCache['key'] ?? null;
			if($promptCacheKey !== null && !is_string($promptCacheKey)) {
				throw new InvalidArgumentException('Invalid conversation session prompt cache key.');
			}

			$options = $promptCache['options'] ?? null;
			if($options !== null) {
				if(!is_array($options) && !is_object($options)) {
					throw new InvalidArgumentException('Invalid conversation session prompt cache options.');
				}
				$promptCacheOptions = PromptCacheOptions::fromArray($options);
			}
		}

		return new self(
			chat: $chat,
			context: $context,
			callableTools: $tools,
			responseFormat: $responseFormat,
			model: $model,
			maxTokens: $maxTokens,
			temperature: $temperature,
			topP: $topP,
			promptCacheKey: $promptCacheKey,
			promptCacheOptions: $promptCacheOptions,
		);
	}

	/**
	 * @param array<int, callable> $tools
	 */
	public static function fromJson(ChatGPT $chat, string $json, array $tools = []): self {
		$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		if(!is_array($payload)) {
			throw new InvalidArgumentException('Conversation session JSON must contain one object.');
		}

		/** @var array<string, mixed> $payload */
		return self::fromArray($chat, $payload, $tools);
	}

	/**
	 * Deserialize a serialized context back into a GPTConversation.
	 *
	 * @param array<int, array<string, mixed>> $payload
	 * @param array<int, callable> $tools
	 */
	public static function fromSerialized(
		ChatGPT $chat,
		array $payload,
		array $tools = [],
		?JsonSchemaResponseFormat $responseFormat = null,
		?ChatModelName $model = null,
		int $maxTokens = 2500,
		?float $temperature = null,
		?float $topP = null,
		?string $promptCacheKey = null,
		?PromptCacheOptions $promptCacheOptions = null,
	): self {
		$context = array_map([self::class, 'decodeMessage'], $payload);

		return new self(
			chat: $chat,
			context: $context,
			callableTools: $tools,
			responseFormat: $responseFormat,
			model: $model,
			maxTokens: $maxTokens,
			temperature: $temperature,
			topP: $topP,
			promptCacheKey: $promptCacheKey,
			promptCacheOptions: $promptCacheOptions,
		);
	}

	/**
	 * @template T of object
	 * @param ChatResponse<T> $response
	 * @return ChatResponseChoice<T>
	 */
	private function absorbResponse(ChatResponse $response): ChatResponseChoice {
		$choice = $response->firstChoice();

		// Append assistant message with any tool calls
		$this->context[] = new ChatOutput(
			result: $choice->result,
			tools: $choice->tools,
			outputItems: $choice->outputItems,
		);

		/** @var ToolCall[] $tools */
		$tools = $choice->tools;

		// Auto-execute callable tools and append ToolResult, but do not re-contact API.
		foreach($tools as $tool) {
			$this->executeToolIfCallable($tool);
		}

		return $choice;
	}

	private function executeToolIfCallable(ToolCall $tool): void {
		$functionName = $tool->name;
		$arguments = $tool->arguments;

		if(is_array($arguments)) {
			$arguments = self::normalizeArguments($arguments);
		}

		if(!isset($this->callableMap[$functionName])) {
			foreach($this->callableTools as $callable) {
				$spec = $this->buildFunctionSpec($callable);
				if($spec['name'] === $functionName) {
					$this->callableMap[$spec['name']] = $callable;
					break;
				}
			}
		}

		if(!isset($this->callableMap[$functionName])) {
			throw new RuntimeException("Missing executable for function {$functionName}.");
		}

		$callable = $this->callableMap[$functionName];

		/** @var array<string, mixed>|string|int|float|bool|null|object $result */
		$result = CallableInvoker::invoke($callable, $arguments);

		$this->context[] = new ToolResult(
			toolCallId: $tool->id,
			content: $result,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function encodeMessage(ChatMessage $message): array {
		if($message instanceof ChatInput) {
			$data = [
				'type' => 'input',
				'content' => $message->content,
				'role' => $message->role,
			];

			if($message->attachment instanceof \DvTeam\ChatGPT\Messages\ChatImageUrl) {
				$data['attachment'] = [
					'type' => 'image_url',
					'url' => $message->attachment->url,
				];
			}

			return $data;
		}

		if($message instanceof ChatOutput) {
			$result = $message->result;

			/** @var ToolCall[] $toolCalls */
			$toolCalls = $message->tools;

			$data = [
				'type' => 'output',
				'result' => $result,
				'tools' => array_map([$this, 'encodeToolCall'], $toolCalls),
			];
			$outputItems = array_map(
				static fn(object $item): mixed => json_decode(JSON::stringify($item), true, 512, JSON_THROW_ON_ERROR),
				$message->outputItems,
			);
			if(count($outputItems)) {
				$data['output_items'] = $outputItems;
			}
			return $data;
		}

		if($message instanceof ToolResult) {
			return [
				'type' => 'tool_result',
				'call_id' => $message->toolCallId,
				'content' => self::normalizeContent($message->content),
			];
		}

		if($message instanceof WebSearchResult) {
			return [
				'type' => 'web_search_result',
				'id' => $message->id,
				'content' => self::normalizeContent($message->content),
			];
		}

		return [
			'type' => 'raw',
			'payload' => $message->jsonSerialize(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function encodeToolCall(ToolCall $tool): array {
		return [
			'id' => $tool->id,
			'name' => $tool->name,
			'arguments' => $tool->arguments,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function decodeMessage(array $data): ChatMessage {
		$type = $data['type'] ?? null;

		return match($type) {
			'input' => new ChatInput(
				content: is_string($data['content'] ?? null) ? $data['content'] : '',
				role: is_string($data['role'] ?? null) ? $data['role'] : 'user'
			),
			'output' => new ChatOutput(
				result: self::normalizeResult($data['result'] ?? null),
				tools: array_map([self::class, 'decodeToolCall'], is_array($data['tools'] ?? null) ? $data['tools'] : []),
				outputItems: self::decodeOutputItems($data['output_items'] ?? []),
			),
			'tool_result' => new ToolResult(
				toolCallId: is_string($data['call_id'] ?? null) ? $data['call_id'] : '',
				content: self::normalizeContent($data['content'] ?? null),
			),
			'web_search_result' => new WebSearchResult(
				id: is_string($data['id'] ?? null) ? $data['id'] : '',
				content: self::normalizeWebSearchContent($data['content'] ?? []),
			),
			default => throw new InvalidResponseException('Unknown message type in serialized conversation.'),
		};
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function decodeToolCall(array $data): ToolCall {
		$id = is_string($data['id'] ?? null) ? $data['id'] : '';
		$name = is_string($data['name'] ?? null) ? $data['name'] : '';
		$arguments = self::normalizeArguments($data['arguments'] ?? []);

		return new ToolCall($id, $name, $arguments);
	}

	/**
	 * @return object[]
	 */
	private static function decodeOutputItems(mixed $raw): array {
		if(is_object($raw)) {
			$raw = (array) $raw;
		}
		if(!is_array($raw)) {
			throw new InvalidResponseException('Invalid serialized output items.');
		}

		$outputItems = [];
		foreach($raw as $item) {
			if(is_array($item)) {
				$item = JSON::parse(JSON::stringify($item));
			}
			if(!is_object($item)) {
				throw new InvalidResponseException('Invalid serialized output item.');
			}
			$outputItems[] = $item;
		}

		return $outputItems;
	}

	/**
	 * @param array<string, mixed>|object $data
	 */
	private static function decodeSessionModel(array|object $data): ChatModelName {
		if(is_object($data)) {
			$data = (array) $data;
		}

		$name = $data['name'] ?? null;
		$supportsTemperature = $data['supports_temperature'] ?? null;
		$supportsTopP = $data['supports_top_p'] ?? null;
		$supportsMaxTokens = $data['supports_max_tokens'] ?? null;
		$effortValue = $data['reasoning_effort'] ?? null;

		if(
			!is_string($name)
			|| !is_bool($supportsTemperature)
			|| !is_bool($supportsTopP)
			|| !is_bool($supportsMaxTokens)
			|| ($effortValue !== null && !is_string($effortValue))
		) {
			throw new InvalidArgumentException('Invalid conversation session model.');
		}

		$effort = $effortValue === null ? null : ReasoningEffort::tryFrom($effortValue);
		if($effortValue !== null && $effort === null) {
			throw new InvalidArgumentException('Invalid conversation session reasoning effort.');
		}

		return new PersistedChatModel(
			name: $name,
			temperatureSupported: $supportsTemperature,
			topPSupported: $supportsTopP,
			maxTokensSupported: $supportsMaxTokens,
			effort: $effort,
		);
	}

	private static function decodeSessionResponseFormat(mixed $data): ?JsonSchemaResponseFormat {
		if($data === null) {
			return null;
		}
		if(is_object($data)) {
			$data = (array) $data;
		}
		if(!is_array($data)) {
			throw new InvalidArgumentException('Invalid conversation session response format.');
		}

		$schema = $data['schema'] ?? null;
		$strict = $data['strict'] ?? null;
		if(!is_array($schema) || !is_bool($strict)) {
			throw new InvalidArgumentException('Invalid conversation session response format.');
		}

		return new JsonSchemaResponseFormat($schema, $strict);
	}

	private static function decodeNullableFloat(mixed $value, string $name): ?float {
		if($value === null) {
			return null;
		}
		if(!is_int($value) && !is_float($value)) {
			throw new InvalidArgumentException("Invalid conversation session {$name}.");
		}

		return (float) $value;
	}

	private static function normalizeArguments(mixed $raw): object {
		if(is_object($raw)) {
			return $raw;
		}

		if(is_array($raw)) {
			$decoded = JSON::parse(JSON::stringify($raw));
			if(!is_object($decoded)) {
				throw new RuntimeException('Invalid serialized arguments.');
			}
			return $decoded;
		}

		if(is_string($raw)) {
			$decoded = JSON::parse($raw);
			if(!is_object($decoded)) {
				throw new RuntimeException('Invalid serialized arguments.');
			}
			return $decoded;
		}

		throw new RuntimeException('Invalid serialized arguments.');
	}

	private static function normalizeResult(mixed $raw): object|string|null {
		if(is_null($raw) || is_string($raw) || is_object($raw)) {
			return $raw;
		}

		if(is_array($raw)) {
			return JSON::stringify($raw);
		}

		if(is_bool($raw) || is_int($raw) || is_float($raw)) {
			return (string) $raw;
		}

		throw new RuntimeException('Invalid result type.');
	}

	/**
	 * @return array<string, mixed>|string|int|float|bool|null|object
	 */
	private static function normalizeContent(mixed $raw): array|string|int|float|bool|null|object {
		if(is_array($raw) || is_object($raw) || is_null($raw) || is_bool($raw) || is_int($raw) || is_float($raw) || is_string($raw)) {
			return $raw;
		}

		throw new RuntimeException('Invalid content type.');
	}

	/**
	 * @return array<string, mixed>|string
	 */
	private static function normalizeWebSearchContent(mixed $raw): array|string {
		if(is_string($raw)) {
			return $raw;
		}

		if(is_array($raw)) {
			/** @var array<string, mixed> $raw */
			return $raw;
		}

		if(is_object($raw)) {
			/** @var array<string, mixed> $arr */
			$arr = (array) $raw;
			return $arr;
		}

		if(is_int($raw) || is_float($raw) || is_bool($raw)) {
			return (string) $raw;
		}

		throw new RuntimeException('Invalid web search content type.');
	}

	private function rebuildFunctionsSpec(): void {
		$wrapped = [];
		$this->callableMap = [];

		foreach($this->callableTools as $callable) {
			$spec = $this->buildFunctionSpec($callable);
			$wrapped[] = $spec['definition'];
			$this->callableMap[$spec['name']] = $callable;
		}

		$this->functionsSpec = count($wrapped) ? new GPTFunctions(...$wrapped) : null;
	}

	private function validateCallable(callable $callable): void {
		$reflection = $this->reflectCallable($callable);
		$attributes = $reflection->getAttributes(\DvTeam\ChatGPT\Attributes\GPTCallableDescriptor::class);
		if(!count($attributes)) {
			throw new RuntimeException('Callable tools used with GPTConversation must have a GPTCallableDescriptor attribute.');
		}
	}

	/**
	 * @return array{name: string, definition: GPTFunction}
	 */
	private function buildFunctionSpec(callable $callable): array {
		$reflection = $this->reflectCallable($callable);
		$descriptorAttr = $reflection->getAttributes(\DvTeam\ChatGPT\Attributes\GPTCallableDescriptor::class)[0] ?? null;
		if($descriptorAttr === null) {
			throw new RuntimeException('Callable tools used with GPTConversation must have a GPTCallableDescriptor attribute.');
		}

		/** @var object{name: string|null, description: string} $descriptor */
		$descriptor = $descriptorAttr->newInstance();
		$name = $descriptor->name ?? CallableNameNormalizer::normalize($reflection->getName());
		$functionDescription = $descriptor->description;

		$properties = [];
		foreach($reflection->getParameters() as $parameter) {
			$paramType = $parameter->getType();
			$typeName = $paramType && method_exists($paramType, 'getName') ? $paramType->getName() : null;
			$paramAttributes = $parameter->getAttributes(\DvTeam\ChatGPT\Attributes\GPTParameterDescriptor::class);
			$structureDefinition = $paramAttributes ? $paramAttributes[0]->newInstance()->definition : null;
			$paramDescription = is_array($structureDefinition) ? ($structureDefinition['description'] ?? null) : null;
			$required = !$parameter->isOptional();

			$parameterDescription = is_string($paramDescription) ? $paramDescription : null;
			$propName = CallableNameNormalizer::normalize($parameter->getName());

			$properties[] = match($typeName) {
				'bool' => new GPTBooleanProperty($propName, $parameterDescription, required: $required),
				'int' => new GPTIntegerProperty($propName, $parameterDescription, required: $required),
				'float' => new GPTNumberProperty($propName, $parameterDescription, required: $required),
				'string' => new GPTStringProperty($propName, $parameterDescription, required: $required),
				default => throw new RuntimeException("Unsupported parameter type for callable tool: {$typeName}"),
			};
		}

		$definition = new GPTFunction(
			name: $name,
			description: $functionDescription,
			properties: new GPTProperties(...$properties)
		);

		return ['name' => $name, 'definition' => $definition];
	}

	private function reflectCallable(callable $callable): ReflectionFunctionAbstract {
		if(is_array($callable)) {
			return new ReflectionMethod($callable[0], $callable[1]);
		}

		if(is_string($callable) && str_contains($callable, '::')) {
			[$class, $method] = explode('::', $callable, 2);
			return new ReflectionMethod($class, $method);
		}

		if(is_object($callable) && method_exists($callable, '__invoke')) {
			return new ReflectionMethod($callable, '__invoke');
		}

		/** @var \Closure|string $callableFn */
		$callableFn = $callable;

		return new ReflectionFunction($callableFn);
	}

}

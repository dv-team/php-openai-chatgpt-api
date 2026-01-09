<?php

namespace DvTeam\ChatGPT;

use DvTeam\ChatGPT\Common\ChatMessage;
use DvTeam\ChatGPT\Common\ChatModelName;
use DvTeam\ChatGPT\Common\JSON;
use DvTeam\ChatGPT\Exceptions\InvalidResponseException;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ChatOutput;
use DvTeam\ChatGPT\MessageTypes\ToolCall;
use DvTeam\ChatGPT\MessageTypes\ToolResult;
use DvTeam\ChatGPT\MessageTypes\WebSearchCall;
use DvTeam\ChatGPT\MessageTypes\WebSearchResult;
use DvTeam\ChatGPT\Response\ChatFuncCallResult;
use DvTeam\ChatGPT\Response\ChatResponse;
use DvTeam\ChatGPT\Response\ChatResponseChoice;
use DvTeam\ChatGPT\ResponseFormat\JsonSchemaResponseFormat;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTBooleanProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTIntegerProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\Reflection\CallableInvoker;
use RuntimeException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionFunctionAbstract;

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
	) {
		$this->setTools($callableTools);
	}

	/**
	 * Execute one round against the API.
	 *
	 * - Calls ChatGPT exactly once.
	 * - Appends the assistant message (and any tool calls) to the context.
	 * - Auto-executes callable tools locally and appends ToolResult, but does NOT
	 *   call the API again. Caller must invoke step() again to continue.
	 */
	public function step(bool $rerunOnToolUse = true): ChatResponseChoice {
		$this->rebuildFunctionsSpec();

		retry:

		$response = $this->chat->chat(
			context: $this->context,
			functions: $this->functionsSpec,
			responseFormat: $this->responseFormat,
			model: $this->model,
			maxTokens: $this->maxTokens,
			temperature: $this->temperature,
			topP: $this->topP,
		);

		$response = $this->absorbResponse($response);

		if($response->isToolCall && $rerunOnToolUse) {
			goto retry;
		}

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
		$this->addMessage(new WebSearchCall(
			id: $toolId,
			query: $query,
			userLocation: $userLocation,
			model: $model,
			effort: $effort,
		));
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
		);
	}

	private function absorbResponse(ChatResponse $response): ChatResponseChoice {
		$choice = $response->firstChoice();

		// Append assistant message with any tool calls
		$this->context[] = new ChatOutput(
			result: $choice->result,
			tools: $choice->tools,
		);

		/** @var ChatFuncCallResult[] $tools */
		$tools = $choice->tools;

		// Auto-execute callable tools and append ToolResult, but do not re-contact API.
		foreach($tools as $tool) {
			$this->executeToolIfCallable($tool);
		}

		return $choice;
	}

	private function executeToolIfCallable(ChatFuncCallResult $tool): void {
		if(!isset($this->callableMap[$tool->functionName])) {
			foreach($this->callableTools as $callable) {
				$spec = $this->buildFunctionSpec($callable);
				if($spec['name'] === $tool->functionName) {
					$this->callableMap[$spec['name']] = $callable;
					break;
				}
			}
		}

		if(!isset($this->callableMap[$tool->functionName])) {
			throw new RuntimeException("Missing executable for function {$tool->functionName}.");
		}

		$callable = $this->callableMap[$tool->functionName];

		/** @var array<string, mixed>|string|int|float|bool|null|object $result */
		$result = CallableInvoker::invoke($callable, $tool->arguments);

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

			/** @var ChatFuncCallResult[] $toolCalls */
			$toolCalls = $message->tools;

			return [
				'type' => 'output',
				'result' => $result,
				'tools' => array_map([$this, 'encodeToolCall'], $toolCalls),
			];
		}

		if($message instanceof ToolCall) {
			return [
				'type' => 'tool_call',
				'id' => $message->id,
				'name' => $message->name,
				'arguments' => $message->arguments,
			];
		}

		if($message instanceof ToolResult) {
			return [
				'type' => 'tool_result',
				'call_id' => $message->toolCallId,
				'content' => self::normalizeContent($message->content),
				'role' => $message->role,
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
	private function encodeToolCall(ChatFuncCallResult $tool): array {
		return [
			'id' => $tool->id,
			'name' => $tool->functionName,
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
				tools: array_map([self::class, 'decodeToolCall'], is_array($data['tools'] ?? null) ? $data['tools'] : [])
			),
			'tool_call' => new ToolCall(
				id: is_string($data['id'] ?? null) ? $data['id'] : '',
				name: is_string($data['name'] ?? null) ? $data['name'] : '',
				arguments: self::normalizeArguments($data['arguments'] ?? []),
			),
			'tool_result' => new ToolResult(
				toolCallId: is_string($data['call_id'] ?? null) ? $data['call_id'] : '',
				content: self::normalizeContent($data['content'] ?? null),
				role: is_string($data['role'] ?? null) ? $data['role'] : 'tool'
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
	private static function decodeToolCall(array $data): ChatFuncCallResult {
		$id = is_string($data['id'] ?? null) ? $data['id'] : '';
		$name = is_string($data['name'] ?? null) ? $data['name'] : '';
		$arguments = self::normalizeArguments($data['arguments'] ?? []);

		return new ChatFuncCallResult(
			id: $id,
			functionName: $name,
			arguments: $arguments,
			toolCallMessage: new ToolCall($id, $name, $arguments)
		);
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
		$name = $descriptor->name ?? $this->normalizeCallableName($reflection->getName());
		$description = $descriptor->description;

		$properties = [];
		foreach($reflection->getParameters() as $parameter) {
			$paramType = $parameter->getType();
			$typeName = $paramType && method_exists($paramType, 'getName') ? $paramType->getName() : null;
			$paramAttributes = $parameter->getAttributes(\DvTeam\ChatGPT\Attributes\GPTParameterDescriptor::class);
			$structureDefinition = $paramAttributes ? $paramAttributes[0]->newInstance()->definition : null;
			$paramDescription = is_array($structureDefinition) ? ($structureDefinition['description'] ?? null) : null;
			$required = !$parameter->isOptional();

			$description = is_string($paramDescription) ? $paramDescription : null;
			$propName = $this->normalizeCallableName($parameter->getName());

			$properties[] = match($typeName) {
				'bool' => new GPTBooleanProperty($propName, $description, required: $required),
				'int' => new GPTIntegerProperty($propName, $description, required: $required),
				'float' => new GPTNumberProperty($propName, $description, required: $required),
				'string' => new GPTStringProperty($propName, $description, required: $required),
				default => throw new RuntimeException("Unsupported parameter type for callable tool: {$typeName}"),
			};
		}

		$definition = new GPTFunction(
			name: $name,
			description: $description ?? '',
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

	private function normalizeCallableName(string $name): string {
		if(str_contains($name, '::')) {
			$name = substr($name, strrpos($name, '::') + 2);
		}

		$name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

		return strtolower((string) $name);
	}
}

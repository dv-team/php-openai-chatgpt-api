<?php

namespace DvTeam\ChatGPT\Reflection;

use DvTeam\ChatGPT\Attributes\GPTFunction;
use DvTeam\ChatGPT\Attributes\GPTMethod;
use DvTeam\ChatGPT\Attributes\GPTParameter;
use DvTeam\ChatGPT\Functions\CallableGPTFunction;
use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\GPTProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTBooleanProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTIntegerProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTNumberProperty;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use ReflectionAttribute;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class GPTCallableFunctionFactory {
	/**
	 * Create a GPT function definition from a PHP callable using reflection and attributes.
	 *
	 * @param callable $callable
	 * @param string|null $nameOverride Optional function name to expose to the LLM.
	 */
	public static function fromCallable(callable $callable, ?string $nameOverride = null): CallableGPTFunction {
		$reflection = self::reflectCallable($callable);

		if($reflection instanceof ReflectionFunction && $reflection->isClosure() && $reflection->getClosureThis() !== null) {
			// For closures bound to object methods, re-reflect as method to access method attributes.
			$reflection = new ReflectionMethod($reflection->getClosureThis(), $reflection->getName());
		} elseif($reflection instanceof ReflectionFunction && $reflection->isClosure() && $reflection->getClosureScopeClass() !== null) {
			$reflection = new ReflectionMethod($reflection->getClosureScopeClass()->getName(), $reflection->getName());
		}

		$name = $nameOverride ?? $reflection->getName();
		$description = self::extractDescription($reflection);

		$properties = new GPTProperties(...self::buildProperties($reflection));

		return new CallableGPTFunction(
			name: $name,
			description: $description,
			properties: $properties,
			callable: $callable
		);
	}

	private static function reflectCallable(callable $callable): ReflectionFunctionAbstract {
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

	/**
	 * @return GPTProperty[]
	 */
	private static function buildProperties(ReflectionFunctionAbstract $reflection): array {
		$properties = [];

		foreach($reflection->getParameters() as $parameter) {
			$type = $parameter->getType();
			$description = self::extractParameterDescription($parameter);
			$required = !$parameter->isOptional();

			if($type instanceof ReflectionNamedType) {
				$typeName = $type->getName();
			} else {
				$typeName = null;
			}

			$name = $parameter->getName();

			$properties[] = match($typeName) {
				'int' => new GPTIntegerProperty(name: $name, description: $description, required: $required),
				'float' => new GPTNumberProperty(name: $name, description: $description, required: $required),
				'bool' => new GPTBooleanProperty(name: $name, description: $description, required: $required),
				default => new GPTStringProperty(name: $name, description: $description, required: $required),
			};
		}

		return $properties;
	}

	private static function extractDescription(ReflectionFunctionAbstract $reflection): string {
		/** @var GPTFunction|GPTMethod|null $attr */
		$attr = self::firstAttributeInstance($reflection, GPTFunction::class, GPTMethod::class);

		return $attr?->description ?? '';
	}

	private static function extractParameterDescription(ReflectionParameter $parameter): ?string {
		/** @var GPTParameter|null $attr */
		$attr = self::firstAttributeInstance($parameter, GPTParameter::class);
		return $attr?->description;
	}

	/**
	 * @param ReflectionFunctionAbstract|ReflectionParameter $ref
	 * @param class-string ...$classes
	 */
	private static function firstAttributeInstance(object $ref, string ...$classes): ?object {
		foreach($classes as $class) {
			/** @var ReflectionAttribute<object>[] $attrs */
			$attrs = $ref->getAttributes($class, ReflectionAttribute::IS_INSTANCEOF);
			if(isset($attrs[0])) {
				return $attrs[0]->newInstance();
			}
		}

		return null;
	}
}

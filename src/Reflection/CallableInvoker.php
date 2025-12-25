<?php

namespace DvTeam\ChatGPT\Reflection;

use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

class CallableInvoker {
	/**
	 * Invoke a callable with ordered arguments extracted from an object of named properties.
	 *
	 * @param callable $callable
	 * @param object $arguments
	 * @return mixed
	 */
	public static function invoke(callable $callable, object $arguments): mixed {
		$reflection = self::reflectCallable($callable);
		$args = [];

		foreach($reflection->getParameters() as $parameter) {
			$name = $parameter->getName();

			if(property_exists($arguments, $name)) {
				$args[] = $arguments->$name;
				continue;
			}

			if($parameter->isOptional()) {
				if($parameter->isDefaultValueAvailable()) {
					$args[] = $parameter->getDefaultValue();
				} else {
					$args[] = null;
				}
				continue;
			}

			throw new RuntimeException("Missing required argument '{$name}' for callable tool.");
		}

		if(is_array($callable) || is_string($callable)) {
			return call_user_func_array($callable, $args);
		}

		return $callable(...$args);
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
}

<?php

namespace DvTeam\ChatGPT\Common;

interface JsonSchemaValidatorInterface {
	/**
	 * Return true or false whenever the schema could be successfully validated against the given json schema. Must not throw an exception.
	 *
	 * @param mixed $data
	 * @param mixed[] $schema
	 * @return bool
	 */
	public function validate(mixed $data, array $schema): bool;
}


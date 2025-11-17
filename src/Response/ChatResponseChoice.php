<?php

namespace DvTeam\ChatGPT\Response;

class ChatResponseChoice {
	/**
	 * @param mixed $result
	 * @param ChatFuncCallResult[] $tools
	 */
	public function __construct(
		public readonly mixed $result,
		public readonly mixed $tools
	) {}
}
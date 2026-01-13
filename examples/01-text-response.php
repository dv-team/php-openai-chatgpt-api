<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\MessageTypes\ChatInput;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$response = $chat->chat(
	context: [
		ChatInput::mk('Write a short haiku about PHP.'),
	]
);

echo $response->firstChoice()->textResult, "\n";


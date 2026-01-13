<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$conversation = new GPTConversation(
	chat: $chat,
	context: [ChatInput::mk('Explain traits in PHP in one paragraph.')]
);

$first = $conversation->step();
echo '1) ', $first->textResult, "\n\n";

$conversation->addMessage(ChatInput::mk('Give one concise code example.'));
$second = $conversation->step();
echo '2) ', $second->textResult, "\n";


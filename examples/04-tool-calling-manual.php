<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\Functions\Function\GPTProperties;
use DvTeam\ChatGPT\Functions\Function\Types\GPTStringProperty;
use DvTeam\ChatGPT\Functions\GPTFunction;
use DvTeam\ChatGPT\Functions\GPTFunctions;
use DvTeam\ChatGPT\MessageTypes\ChatInput;
use DvTeam\ChatGPT\MessageTypes\ToolResult;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$orders = [
	'A100' => [
		'order_id' => 'A100',
		'status' => 'shipped',
		'eta' => '2026-01-16',
		'carrier' => 'UPS',
	],
	'C300' => [
		'order_id' => 'C300',
		'status' => 'processing',
		'eta' => '2026-01-18',
		'carrier' => null,
	],
];

$functions = new GPTFunctions(
	new GPTFunction(
		name: 'get_order_by_id',
		description: 'Returns order status and ETA for a single order ID.',
		properties: new GPTProperties(
			new GPTStringProperty('order_id', 'Order ID like A100.', required: true)
		)
	)
);

$context = [ChatInput::mk('I have orders A100 and C300. Use get_order_by_id for each order and then summarize the statuses in 2-3 sentences.')];

$step1 = $chat->chat(context: $context, functions: $functions)->firstChoice();
$context[] = $step1->getChatOutput();

if(count($step1->tools) === 0) {
	throw new RuntimeException('Model did not call any tool.');
}

foreach($step1->tools as $tool) {
	if($tool->name !== 'get_order_by_id') {
		throw new RuntimeException(sprintf('Unexpected tool called: %s', $tool->name));
	}

	$orderId = $tool->arguments->order_id ?? null;
	if(!is_string($orderId) || $orderId === '') {
		$context[] = new ToolResult($tool->id, ['error' => 'Missing order_id']);
		continue;
	}

	$order = $orders[$orderId] ?? null;
	if($order === null) {
		$context[] = new ToolResult($tool->id, ['error' => 'Unknown order_id', 'order_id' => $orderId]);
		continue;
	}

	$context[] = new ToolResult($tool->id, $order);
}

$context[] = ChatInput::mk('Using only the tool results above, write the summary now.');
$step2 = $chat->chat(context: $context, functions: $functions)->firstChoice();
echo $step2->textResult, "\n";

<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\Attributes\GPTCallableDescriptor;
use DvTeam\ChatGPT\Attributes\GPTParameterDescriptor;
use DvTeam\ChatGPT\GPTConversation;
use DvTeam\ChatGPT\MessageTypes\ChatInput;

/** @var \DvTeam\ChatGPT\ChatGPT $chat */
$chat = require __DIR__ . '/_bootstrap.php';

$calendar = [
	'2026-01-15' => ['09:00', '10:30', '14:00'],
];

$slotLookups = [];

$conversation = new GPTConversation(
	$chat,
	[ChatInput::mk(
		'Book a 30-minute meeting titled "Project kickoff" on 2026-01-15 at the earliest available time. ' .
		'Always call get_free_slots first. After booking, confirm in one sentence with meeting_id, date and time.'
	)],
	callableTools: [
		#[GPTCallableDescriptor(name: 'get_free_slots', description: 'Returns free time slots for a given date (YYYY-MM-DD).')]
		function(#[GPTParameterDescriptor(['description' => 'Date in YYYY-MM-DD.'])] string $date) use (&$calendar, &$slotLookups): array {
			$slotLookups[$date] = true;

			return [
				'date' => $date,
				'slots' => $calendar[$date] ?? [],
			];
		},

		#[GPTCallableDescriptor(name: 'book_meeting', description: 'Books a meeting if the slot is available.')]
		function(
			#[GPTParameterDescriptor(['description' => 'Date in YYYY-MM-DD.'])] string $date,
			#[GPTParameterDescriptor(['description' => 'Time in HH:MM (24h).'])] string $time,
			#[GPTParameterDescriptor(['description' => 'Meeting title.'])] string $title,
		) use (&$calendar, &$slotLookups): array {
			if(!($slotLookups[$date] ?? false)) {
				return ['ok' => false, 'error' => 'Call get_free_slots first.'];
			}

			$slots = $calendar[$date] ?? [];
			$idx = array_search($time, $slots, true);
			if($idx === false) {
				return ['ok' => false, 'error' => 'Slot not available.'];
			}

			unset($slots[$idx]);
			$calendar[$date] = array_values($slots);

			return [
				'ok' => true,
				'meeting_id' => uniqid('mtg_', true),
				'date' => $date,
				'time' => $time,
				'title' => $title,
			];
		},
	],
);

$maxSteps = 5;
$choice = null;

for($i = 0; $i < $maxSteps; $i++) {
	$choice = $conversation->step(); // one API call; tools run locally and results are appended
	if(!$choice->isToolCall) {
		break;
	}
}

if($choice === null || $choice->isToolCall) {
	throw new RuntimeException('Conversation did not produce a final assistant message.');
}

echo $choice->textResult, "\n";

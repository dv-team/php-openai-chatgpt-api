<?php

declare(strict_types = 1);

use DvTeam\ChatGPT\ChatGPT;
use DvTeam\ChatGPT\Http\Psr18HttpClient;
use DvTeam\ChatGPT\OpenAIToken;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

require __DIR__ . '/../vendor/autoload.php';

$apiKey = getenv('OPENAI_API_KEY') ?: '';
if($apiKey === '') {
	fwrite(STDERR, "Missing OPENAI_API_KEY env var.\n");
	exit(1);
}

$http = Psr18HttpClient::create(
	client: new GuzzleClient(),
	requestFactory: new HttpFactory(),
	streamFactory: new HttpFactory(),
);

return new ChatGPT(
	token: new OpenAIToken($apiKey),
	httpPostClient: $http,
);


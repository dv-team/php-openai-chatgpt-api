<?php

namespace DvTeam\ChatGPT\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class GuzzleHttpClient implements HttpPostInterface {
	public function create(int $readTimeout = 0, int $connectTimeout = 5): GuzzleHttpClient {
		$client = new Client([
			RequestOptions::HEADERS => ['accept' => 'application/json; charset=utf-8'],
			RequestOptions::TIMEOUT => $readTimeout,
			RequestOptions::READ_TIMEOUT => $readTimeout,
			RequestOptions::CONNECT_TIMEOUT => $connectTimeout,
		]);
		return new self($client);
	}

	public function __construct(private readonly Client $client) {}

	public function post(string $url, array $data, array $headers): string {
		try {
			$response = $this->client->post($url, ['json' => $data, 'headers' => $headers]);
			return $response->getBody()->getContents();
		} catch (ClientException $e) {
			$content = $e->getResponse()->getBody()->getContents();
			$headers = $e->getResponse()->getHeaders();
			$statusCode = $e->getResponse()->getStatusCode();
			throw new LLMNetworkException(contents: $content, headers: $headers, statusCode: $statusCode, previous: $e);
		} catch (GuzzleException $e) {
			throw new LLMException($e->getMessage(), $e->getCode(), $e);
		}
	}
}

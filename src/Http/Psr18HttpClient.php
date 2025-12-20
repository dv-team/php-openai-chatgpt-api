<?php

namespace DvTeam\ChatGPT\Http;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Psr18HttpClient implements HttpPostInterface {
	/**
	 * @param array<string, string> $defaultHeaders
	 */
	public static function create(
		ClientInterface $client,
		RequestFactoryInterface $requestFactory,
		StreamFactoryInterface $streamFactory,
		array $defaultHeaders = ['accept' => 'application/json; charset=utf-8'],
	): Psr18HttpClient {
		return new self($client, $requestFactory, $streamFactory, $defaultHeaders);
	}

	/**
	 * @param array<string, string> $defaultHeaders
	 */
	public function __construct(
		private readonly ClientInterface $client,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory,
		private readonly array $defaultHeaders = ['accept' => 'application/json; charset=utf-8'],
	) {}

	public function post(string $url, array $data, array $headers): string {
		$mergedHeaders = array_merge($this->defaultHeaders, $headers);

		if(!isset($mergedHeaders['Content-Type']) && !isset($mergedHeaders['content-type'])) {
			$mergedHeaders['Content-Type'] = 'application/json';
		}

		try {
			$json = json_encode($data, JSON_THROW_ON_ERROR);
		} catch(JsonException $e) {
			throw new LLMException($e->getMessage(), $e->getCode(), $e);
		}

		$request = $this->requestFactory
			->createRequest('POST', $url)
			->withBody($this->streamFactory->createStream($json));

		foreach($mergedHeaders as $name => $value) {
			$request = $request->withHeader($name, $value);
		}

		try {
			$response = $this->client->sendRequest($request);
		} catch(NetworkExceptionInterface $e) {
			throw new LLMNetworkException(contents: '', headers: [], statusCode: 0, previous: $e);
		} catch(ClientExceptionInterface $e) {
			throw new LLMException($e->getMessage(), $e->getCode(), $e);
		}

		$content = (string) $response->getBody();
		$statusCode = $response->getStatusCode();
		$responseHeaders = $response->getHeaders();

		if($statusCode >= 400) {
			throw new LLMNetworkException(contents: $content, headers: $responseHeaders, statusCode: $statusCode);
		}

		return $content;
	}
}

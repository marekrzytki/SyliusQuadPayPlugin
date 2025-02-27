<?php

/*
 * This file was created by developers working at BitBag
 * Do you need more information about us and what we do? Visit our https://bitbag.io website!
 * We are hiring developers from all over the world. Join us and start your new, exciting adventure and become part of us: https://bitbag.io/career
*/

declare(strict_types=1);

namespace BitBag\SyliusQuadPayPlugin\Client;

use GuzzleHttp\ClientInterface;

class QuadPayApiClient implements QuadPayApiClientInterface
{
    /** @var ClientInterface */
    protected $apiClient;

    /** @var string */
    protected $clientId;

    /** @var string */
    protected $clientSecret;

    /** @var string */
    protected $apiEndpoint;

    /** @var string */
    protected $authTokenEndpoint;

    /** @var string */
    protected $apiAudience;

    public function __construct(ClientInterface $client)
    {
        $this->apiClient = $client;
    }

    public function setConfig(
        string $clientId,
        string $clientSecret,
        string $apiEndpoint,
        string $authTokenEndpoint,
        string $apiAudience
    ): void {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiEndpoint = $apiEndpoint;
        $this->authTokenEndpoint = $authTokenEndpoint;
        $this->apiAudience = $apiAudience;
    }

    public function getOrderUrl(?string $orderId = null, ?string $orderToken = null): string
    {
        $url = sprintf('https://%s/order', parse_url($this->apiEndpoint)['host']);

        if (null !== $orderToken) {
            return sprintf('%s?token=%s', $url, $orderToken);
        }

        if (null !== $orderId) {
            return sprintf('%s/%s', $url, $orderId);
        }

        return $url;
    }

    public function getRefundUrl(string $orderId): string
    {
        return sprintf('https://%s/order/%s/refund', parse_url($this->apiEndpoint)['host'], $orderId);
    }

    public function getOauthTokenUrl(): string
    {
        return sprintf('https://%s/oauth/token', parse_url($this->authTokenEndpoint)['host']);
    }

    public function createAccessToken(): array
    {
        return $this->request('POST', $this->getOauthTokenUrl(), [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'audience' => $this->apiAudience,
            'grant_type' => 'client_credentials',
        ]);
    }

    public function createOrder(array $data): array
    {
        return $this->request('POST', $this->getOrderUrl(), $data, $this->createAccessToken()['access_token']);
    }

    public function getOrderByToken(string $orderToken): array
    {
        return $this->request('GET', $this->getOrderUrl(null, $orderToken), [], $this->createAccessToken()['access_token']);
    }

    public function getOrderById(string $orderId): array
    {
        return $this->request('GET', $this->getOrderUrl($orderId), [], $this->createAccessToken()['access_token']);
    }

    public function refund(
        float $amount,
        string $merchantRefundReference,
        string $orderToken,
        ?string $orderId = null
    ): array {
        if (null === $orderId) {
            $orderId = $this->getOrderByToken($orderToken)['orderId'];
        }

        $data = [
            'amount' => $amount,
            'merchantRefundReference' => $merchantRefundReference,
        ];

        return $this->request('POST', $this->getRefundUrl($orderId), $data, $this->createAccessToken()['access_token']);
    }

    protected function getHeaders(?string $accessToken = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (null !== $accessToken) {
            $headers['Authorization'] = sprintf('Bearer %s', $accessToken);
        }

        return $headers;
    }

    protected function request(string $method, string $url, array $data = [], ?string $accessToken = null): array
    {
        $options = [
            'json' => $data,
            'headers' => $this->getHeaders($accessToken),
        ];

        $result = $this->apiClient->request($method, $url, $options);

        return \GuzzleHttp\json_decode((string) $result->getBody(), true);
    }
}

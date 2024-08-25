<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

$apiSecrets = [
   "{{api-key}}" => "{{secret-key}}",
];

function getApiSecret(string $apiKey, array $apiSecrets): string
{
    $secret = base64_decode($apiSecrets[$apiKey] ?? '');
    return $secret;
}

function calculateSignature(string $endpoint, string $timestamp, string $body, string $secret): string
{
    $body = json_encode(json_decode($body, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Removendo quebras de linha e espaços adicionais
    // $body = preg_replace('/\s+/', '', $body);

    // Exibindo o resultado no console (sem afetar a resposta da API)
    file_put_contents('php://stderr', print_r($timestamp, true));
    file_put_contents('php://stderr', print_r($secret, true));
    file_put_contents('php://stderr', print_r($endpoint, true));
    file_put_contents('php://stderr', print_r($body, true));

    $hmac = hash_hmac(
        'sha256',
        $timestamp . $endpoint . $body,
        $secret,
        true
    );

    return base64_encode($hmac);
}

function checkSignature(Request $request, array $apiSecrets): array
{
    $headers = $request->getHeaders();
    $endpoint = $headers['X-Endpoint'][0];
    $timestamp = $headers['X-Timestamp'][0];
    $receivedSignature = str_replace('hmac-sha256 ', '', $headers['X-Signature'][0]);
    $secret = getApiSecret($headers['X-Api-Key'][0], $apiSecrets);

    $body = (string)$request->getBody();
    $calculatedSignature = calculateSignature($endpoint, $timestamp, $body, $secret);

    if (!hash_equals($receivedSignature, $calculatedSignature)) {
        return [false, $calculatedSignature];
    }

    return [true, $calculatedSignature];
}

function signResponse(Request $request, ?string $body, string $secret): array
{
    $headers = $request->getHeaders();
    $endpoint = $headers['X-Endpoint'][0];
    $timestamp = (string)time();

    file_put_contents('php://stderr', print_r($timestamp, true));
    file_put_contents('php://stderr', print_r($secret, true));
    file_put_contents('php://stderr', print_r($endpoint, true));
    file_put_contents('php://stderr', print_r($body, true));
 

    $hmac = hash_hmac(
        'sha256',
        $timestamp . $endpoint . $body,
        $secret,
        true
    );

    $signature = 'hmac-sha256 ' . base64_encode($hmac);

    return [$timestamp, $signature];
}

$app->post('/transactions/authorizations', function (Request $request, Response $response) use ($apiSecrets) {
    return handleTransaction($request, $response, $apiSecrets);
});

$app->post('/transactions/adjustments', function (Request $request, Response $response) use ($apiSecrets) {
    return handleTransaction($request, $response, $apiSecrets);
});

function handleTransaction(Request $request, Response $response, array $apiSecrets): Response
{
    [$signatureValid, $calculatedSignature] = checkSignature($request, $apiSecrets);

    $responseData = [
        'Status' => $signatureValid ? 'APPROVED' : 'DENIED',
        'StatusDetail' => $signatureValid ? 'APPROVED' : 'DENIED',
        'Message' => 'OK'
    ];

    if (!$signatureValid) {
        $responseData['Message'] = sprintf(
            'Signature mismatch. Received %s, calculated %s',
            $request->getHeaderLine('X-Signature'),
            $calculatedSignature
        );
    }

    $responseJson = json_encode($responseData, JSON_UNESCAPED_SLASHES);

    $secret = getApiSecret($request->getHeaderLine('X-Api-Key'), $apiSecrets);
    [$timestamp, $signature] = signResponse($request, $responseJson, $secret);

    $response->getBody()->write($responseJson);

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('X-Endpoint', $request->getHeaderLine('X-Endpoint'))
        ->withHeader('X-Timestamp', $timestamp)
        ->withHeader('X-Signature', $signature);
}

$app->run();


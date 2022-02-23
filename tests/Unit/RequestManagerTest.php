<?php

use Psr\Http\Message\RequestInterface;
use Sammyjo20\Saloon\Http\MockResponse;
use Sammyjo20\Saloon\Clients\MockClient;
use GuzzleHttp\Exception\RequestException;
use Sammyjo20\Saloon\Managers\RequestManager;
use Sammyjo20\Saloon\Tests\Resources\Requests\UserRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\ErrorRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\HeaderRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\UserRequestWithBoot;
use Sammyjo20\Saloon\Tests\Resources\Requests\ReplaceConfigRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\ReplaceHeaderRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\TrailingSlashRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\NoTrailingSlashRequest;
use Sammyjo20\Saloon\Tests\Resources\Requests\UserRequestWithBootConnector;

test('a request is built up correctly', function () {
    $requestManager = new RequestManager(new HeaderRequest());

    // Manually prepare the message

    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toEqual([
        'Accept' => 'application/json',
        'X-Connector-Header' => 'Sam', // Added by connector
        'X-Custom-Header' => 'Howdy', // Added by request
    ]);

    expect($requestManager->getConfig())->toEqual([
        'json' => [
            'foo' => 'bar', // Added by feature
        ],
        'http_errors' => false, // Added manually in connector
        'timeout' => 5, // Added manually in request
        'debug' => true, // Added by connector feature
    ]);
});

test('a request headers replace connectors headers', function () {
    $requestManager = new RequestManager(new ReplaceHeaderRequest());

    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Connector-Header', 'Howdy');
});

test('a request config replace connectors config', function () {
    $requestManager = new RequestManager(new ReplaceConfigRequest());

    $requestManager->hydrate();

    expect($requestManager->getConfig())->toHaveKey('debug', false);
});

test('the boot method can add functionality in connectors', function () {
    $requestManager = new RequestManager(new UserRequestWithBootConnector());
    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Connector-Boot-Header', 'Howdy!');
});

test('the boot method can add functionality in requests', function () {
    $requestManager = new RequestManager(new UserRequestWithBoot());
    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Request-Boot-Header', 'Yee-haw!');
});


test('the boot method has access to the current request within a connector', function () {
    $requestManager = new RequestManager(new UserRequestWithBoot());
    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Connector-Request-Class', UserRequestWithBoot::class);
});

test('the boot method has access to the current request within a request', function () {
    $requestManager = new RequestManager(new UserRequestWithBoot());
    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Request-Boot-With-Data', 'Ride on, cowboy.');

    $farewell = 'Farewell, Sir!';
    $requestManager = new RequestManager(new UserRequestWithBoot($farewell));
    $requestManager->hydrate();

    expect($requestManager->getHeaders())->toHaveKey('X-Request-Boot-With-Data', $farewell);
});

test('the requirement for a trailing slash is enabled by default', function () {
    $request = new TrailingSlashRequest();

    $request->addHandler('captureBaseUrl', function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            expect($request->getUri()->getPath())->toEqual('/api/');

            return $handler($request, $options);
        };
    });

    $request->send();
});

test('the trailing slash is removed when disabled from the request if the endpoint is an empty string', function () {
    $request = new NoTrailingSlashRequest();

    $request->addTrailingSlashAfterBaseUrl = false;

    $request->addHandler('captureBaseUrl', function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            expect($request->getUri()->getPath())->toEqual('/api');

            return $handler($request, $options);
        };
    });

    $request->send();
});

test('it cant detect laravel', function () {
    $requestManager = new RequestManager(new UserRequest());

    expect($requestManager->inLaravelEnvironment)->toBeFalse();
});

test('if you do not pass a mock client into the request, no mocking will be configured', function () {
    $requestManager = new RequestManager(new UserRequest());

    expect($requestManager->isMocking())->toBeFalse();
});

test('if you pass a mock client into the request, the request manager will setup mocking correctly', function () {
    $mockClient = new MockClient([MockResponse::make([], 200)]);

    $requestManager = new RequestManager(new UserRequest(), $mockClient);

    expect($requestManager->isMocking())->toBeTrue();
});

test('if a request fails you can get the original error from the response', function () {
    $request = new ErrorRequest();

    $response = $request->send();

    expect($response->getGuzzleException())->toBeInstanceOf(RequestException::class);
});

test('if a request fails you can get the original error from the SaloonException', function () {
    $request = new ErrorRequest();

    $response = $request->send();

    expect($response->toException()->getPrevious())->toBeInstanceOf(RequestException::class);
});

<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rollerworks\Component\Version\Version as Semver;

$app = new \Slim\App();

require './src/actions/Upload.php';
require './src/actions/TempPath.php';

require './src/routes/Extract.php';
require './src/routes/Compile.php';

$app->add(function ($request, $response, $next) {
    $response = $next(
        $request,
        $response->withHeader('Access-Control-Allow-Origin', '*')
    );
    return $response;
});

$container = $app->getContainer();

$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        return $response->withStatus(404)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 404, 'errorReason' => 'notFound'],
            'message' => 'Not Found'
        ));
    };
};

$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        return $response->withStatus(500)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 500, 'errorReason' => 'backendError'],
            'message' => 'Backend Error'
        ));
    };
};

$container['phpErrorHandler'] = function ($container) {
    return $container['errorHandler'];
};

$app->run();

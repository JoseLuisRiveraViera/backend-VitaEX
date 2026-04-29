<?php
declare(strict_types=1);

use app\controllers\HealthController;
use app\controllers\UserController;
use app\middlewares\SecurityHeadersMiddleware;
use flight\Engine;
use flight\net\Router;

/**
 * @var Router $router
 * @var Engine $app
 */

$router->group('', function (Router $router) use ($app): void {
	$router->get('/', function () use ($app): void {
		$app->render('welcome', ['message' => 'VitaEX API']);
	});

	$router->group('/api', function (Router $router): void {
		$router->get('/health', [HealthController::class, 'index']);

		$router->get('/users', [UserController::class, 'index']);
		$router->get('/users/@id:[0-9]+', [UserController::class, 'show']);
		$router->post('/users', [UserController::class, 'store']);
		$router->put('/users/@id:[0-9]+', [UserController::class, 'update']);
		$router->patch('/users/@id:[0-9]+', [UserController::class, 'update']);
		$router->delete('/users/@id:[0-9]+', [UserController::class, 'destroy']);
	});
}, [SecurityHeadersMiddleware::class]);

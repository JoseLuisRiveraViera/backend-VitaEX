<?php
declare(strict_types=1);

use app\controllers\HealthController;
use app\middlewares\SecurityHeadersMiddleware;
use flight\Engine;
use flight\net\Router;

/**
 * @var Router $router
 * @var Engine $app
 */

$router->group('', function (Router $router) use ($app): void {
	$router->get('/', function () use ($app): void {
		$app->render('welcome', ['message' => 'Bolsa de Trabajo UT de la Costa API']);
	});
}, [SecurityHeadersMiddleware::class]);

require dirname(__DIR__) . '/routes/api.php';

<?php

use Frugal\Core\Commands\CommandInterpreter;
use Frugal\Core\Services\Bootstrap;
use Psr\Http\Message\ResponseInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;

define('START_TS', microtime(true));
define('MEMORY_ON_START', memory_get_usage(true));
define('ROOT_DIR', __DIR__);

require(__DIR__.'/vendor/autoload.php');

// Mode commande
if($_SERVER['argc'] > 1) {
    return CommandInterpreter::run();
}

// Bootstrap
Bootstrap::loadEnv();
if(getenv('SERVER_HOST') === false || getenv('SERVER_PORT') === false) {
    echo "\n⚠️ --- Server need SERVER_HOST and SERVER_PORT in .env defined to start.\nAbort.\n\n";
    die;
}

$router = Bootstrap::compileRoute();

$loop = React\EventLoop\Loop::get();

$server = new HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($router) {
    $startRequestTS = microtime(true);
    return $router->dispatch($request)
        ->then(
            onFulfilled: 
                function(ResponseInterface $response) use ($request, $startRequestTS) {
                    $method = $request->getMethod();
                    $uri = $request->getUri()->getPath();
                    $memoryPeak = memory_get_peak_usage(true)/1024/1024;
                    $delay = round(microtime(true) - $startRequestTS,4);

                    echo "✅ URL : [$method] ".$uri."\n";
                    echo "🧠 Mémoire en peak : ".$memoryPeak." Mb\n";
                    echo "🕒 Temps execution : ".$delay."s";

                    return $response;
                },
            onRejected:
                function(Throwable $e) use ($request, $startRequestTS) {
                    $method = $request->getMethod();
                    $uri = $request->getUri()->getPath();
                    $delay = round(microtime(true) - $startRequestTS,4);

                    echo "❌ URL : [$method] ".$uri." (404) \n";
                    echo "🕒 Temps execution : ".$delay."s\n\n";

                    return new Response(Response::STATUS_NOT_FOUND);
                }
            );
});

$socket = new React\Socket\SocketServer(getenv('SERVER_HOST').":".getenv('SERVER_PORT'));
$server->listen($socket);
$memoryPeak = memory_get_peak_usage(true)/1024/1024;
$startDelay = round(microtime(true) - START_TS,4);


echo "\n✅ Serveur lancé sur http://".getenv('SERVER_HOST').":".getenv('SERVER_PORT')."\n";
echo "🕒 Lancement en ".$startDelay."s\n";
echo "🧠 Mémoire consommée : ".$memoryPeak." Mb\n\n";
$loop->run();
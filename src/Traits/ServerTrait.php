<?php

namespace Tiimber\Traits\Server;

use React\EventLoop\Factory;
use React\Promise\Promise;
use React\Socket\Server as Socket;
use React\Http\{Server as Http, Request, Response};

use Symfony\Component\Routing\Exception\RouteNotFoundException;

use Tiimber\{Config, Dispatcher, Memory, Renderer, Traits\LoggerTrait, Http\Request as TiRequest};

use const Tiimber\Consts\Scopes\{HTTP, LAYOUT};
use const Tiimber\Consts\Http\{PORT, HOST, CODE, HEADER, DEFAULT_HEADERS};
use const Tiimber\Consts\Events\{ERROR, RENDER, REQUEST};

trait ReactTrait
{
  use LoggerTrait;

  private $request;

  private $routes;

  public function runHttpServer(callable $callback)
  {
    $this->dispatcher = new Dispatcher();
    $this->routes = Config::get('routes', []);

    $loop = Factory::create();
    $socket = new Socket($loop);
    $http = new Http($socket);
    $http->on('request', $callback);
    
    Memory::create(HTTP);
    $socket->listen(
      Memory::get(HTTP)->get(PORT, '1337'),
      Memory::get(HTTP)->get(HOST, '127.0.0.1')
    );
    
    Memory::events()->on('stop::rendering', function () {
      Memory::get(HTTP)->set(CODE, 200); 
      Memory::get(HTTP)->set(HEADER, DEFAULT_HEADERS);
    });
    
    $loop->run();
  }

  public function runApp(): callable
  {
    return function (Request $request, Response $response) {
      Memory::events()->emit('on::request', []);
      try {
        $this->log('info', 'new ' . $request->getMethod() . ' request on ' . $request->getPath());
        
        Memory::events()->once('response::end', function ($content) use ($response) {
          $this->log('info', 'Response code ' . Memory::get(HTTP)->get(CODE, 200));
          $this->log('info', 'Response Header ' . print_r(Memory::get(HTTP)->get(HEADER, DEFAULT_HEADERS), true));

          $response->writeHead(
            Memory::get(HTTP)->get(CODE, 200), 
            Memory::get(HTTP)->get(HEADER, DEFAULT_HEADERS)
          );

          $response->end($content);
          
          Memory::events()->emit('stop::rendering', []);
        });

        if ($request->getMethod() === 'POST') {
          $request->on('data', function ($data) use ($request, $response) {
            $tiRequest = new TiRequest($request, $data);
            $this->emitRequest($tiRequest, $response);
          });
        } else {
          $this->emitRequest($request, $response);
        }
      } catch (\Exception $e) {
        $this->log('error', $e->getMessage());
        $this->log('error', 'Trace : ' . "\n" . $e->getTraceAsString());
      }
    };
  }
  
  private function emitRequest($request, $response)
  {
    $render = new Renderer();
    try {
      $match = $this->resolve($this->routes, $request->getMethod(), $request->getPath());

      $this->dispatcher->emit('request', strtolower($match['_route']), $render, [
        'request' => $request,
        'args' => $match
      ]);
    } catch (RouteNotFoundException $e) {
       $this->dispatcher->emit('error', '404', $render, [
        'request' => $request,
        'args' => []
      ]);
      Memory::get(HTTP)->set(CODE, 500);
    } catch (\Exception $e) {
      $this->log('error', $e->getMessage());
      $this->log('error', $e->getTraceAsString());
      $this->dispatcher->emit('error', '500', $render, [
        'request' => $request,
        'args' => ['error' => $e]
      ]);
      Memory::get(HTTP)->set(CODE, 500);
    }

    $layout = Memory::get(LAYOUT)->get('\\Blog\\Layouts\\DefaultLayout');
    Memory::events()->emit('response::end', ['content' => $render->render($layout)]);
  }
  
  public function setHost(string $host)
  {
    Memory::set(HTTP)->set(HOST, $host);
  }

  public function setPort(int $port)
  {
    Memory::set(HTTP)->set(PORT, $port);
  }
}
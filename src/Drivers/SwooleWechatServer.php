<?php

/**
 * Class SwooleWechatServer
 * @package Commune\Chatbot\Wechat\Server
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\Drivers;


use Commune\Chatbot\Blueprint\Application;
use Commune\Chatbot\Blueprint\Conversation\Conversation;
use Commune\Chatbot\Contracts\ChatServer;
use Commune\Chatbot\Wechat\Component\WechatComponent;
use Commune\Chatbot\Wechat\EasyWechat\Wechat;
use Illuminate\Support\Facades\Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Simple\RedisCache;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;

class SwooleWechatServer implements ChatServer
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var WechatComponent
     */
    protected $config;

    /**
     * SwooleWechatServer constructor.
     * @param Application $app
     * @param WechatComponent $config
     */
    public function __construct(Application $app, WechatComponent $config)
    {
        $this->app = $app;
        $this->config = $config;
        $this->server = new \Swoole\Http\Server(
            $config->serverIp,
            $config->serverPort
        );

    }


    protected function bootstrap() : void
    {
        $this->server->on("start", function ($server) {
            $ip = $this->config->serverIp;
            $port = $this->config->serverPort;
            $this->app
                ->getConsoleLogger()
                ->info("Swoole http server is started at $ip:$port");
        });

        $reactor = $this->app->getReactorContainer();

        $this->server->on(
            "request",
            function (Request $request, Response $response) use ($reactor){

                $server = new Wechat($reactor['config']['wechat']);

                // request
                $symfonyRequest = $this->transformRequest($request);
                $server->rebind('request', $symfonyRequest);
                // log
                $logger = $reactor[LoggerInterface::class];
                $server->rebind('log', $logger);
                // cache
                $predis = Redis::connection()->client();
                $server->rebind('cache', new RedisCache($predis));

                $this->setMessageHandler($server);
                // serve
                $symfonyResponse = $server->server->serve();
                $response->end($symfonyResponse->getContent());
            }
        );
    }

    /**
     * @param Wechat $wechat
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    protected function setMessageHandler(Wechat $wechat)
    {
        $wechat->server->push(function($message){
            return 'hello';
        });
    }

    protected function transformRequest(Request $request) : SymfonyRequest
    {
        return new SymfonyRequest(
            $uri = $request->server['request_uri'],
            $method = $request->server['request_method'],
            [],
            $request->cookie,
            $request->files,
            $request->server,
            $request->rawContent()
        );
    }



    public function run(): void
    {
        $this->bootstrap();
        $this->server->start();
    }

    public function sleep(int $millisecond): void
    {
        Coroutine::sleep(ceil($millisecond / 1000));
    }

    public function fail(): void
    {
        $this->server->shutdown();
    }

    public function closeClient(Conversation $conversation): void
    {
    }


}
<?php

/**
 * Class SwooleWechatServer
 * @package Commune\Chatbot\Wechat\Server
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\OfficialAccount;


use Commune\Chatbot\Blueprint\Application;
use Commune\Chatbot\Blueprint\Conversation\Conversation;
use Commune\Chatbot\Contracts\ChatServer;
use Commune\Chatbot\Contracts\ConsoleLogger;
use EasyWeChat\OfficialAccount\Application as Wechat;
use Illuminate\Support\Facades\Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Simple\RedisCache;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;

class SwooleOfficialAccountServer implements ChatServer
{
    protected static $server;
    /**
     * @var Application
     */
    protected $app;


    /**
     * @var array
     */
    protected $config;

    /**
     * SwooleWechatServer constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        if (!isset(self::$server)) {

            $this->config = $app->getReactorContainer()['config']['commune']['wechat'];
            self::$server = new \Swoole\Http\Server(
                $this->config['serverIp'],
                $this->config['serverPort']
            );
        }

    }


    protected function bootstrap() : void
    {
        self::$server->on("start", function ($server) {
            $ip = $this->config['serverIp'];
            $port = $this->config['serverPort'];
            $this->app
                ->getConsoleLogger()
                ->info("Swoole http server is started at $ip:$port");
        });

        $reactor = $this->app->getReactorContainer();
        $reactor->singleton(
            ChatServer::class,
            SwooleOfficialAccountServer::class
        );

        self::$server->on(
            "request",
            function (Request $request, Response $response) {

                try {

                    $server = new Wechat($this->config);

                    // request
                    $symfonyRequest = $this->transformRequest($request);
                    $server->rebind('request', $symfonyRequest);
                    // log
                    $logger = $this->app->getConsoleLogger();
                    $server->rebind('logger', $logger);
                    $server->rebind('log', $logger);
                    // cache
                    $predis = Redis::connection()->client();
                    $server->rebind('cache', new RedisCache($predis));

                    $this->setMessageHandler($server);
                    // serve
                    $symfonyResponse = $server->server->serve();
                    $response->end($symfonyResponse->getContent());

                } catch (\Throwable $e) {
                    $this->app
                        ->getConsoleLogger()
                        ->error($e);

                }
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
        $wechat->server->push(function($message) use ($wechat){
            switch ($message['MsgType']) {
                case 'text':
                case 'event':
                    $request = new OfficialAccountRequest($wechat, $message);
                    $this->app
                        ->getKernel()
                        ->onUserMessage($request);
                    return $request->getOutput();

                    break;
                case 'image':
                case 'voice':
                case 'video':
                case 'location':
                case 'link':
                case 'file':
                default:
                    return '暂时不支持的消息';
            }

        });
    }

    protected function transformRequest(Request $swRequest) : SymfonyRequest
    {
        $query = $swRequest->get ?? [];
        $request = $swRequest->post ?? [];
        $cookie = $swRequest->cookie ?? [];
        $files = $swRequest->files ?? [];
        $content = $swRequest->rawContent() ?: null;

        $server = array_change_key_case($swRequest->server, CASE_UPPER);
        foreach ($swRequest->header as $key => $val) {
            $server[sprintf('HTTP_%s', strtoupper(str_replace('-', '_', $key)))] = $val;
        }

        return new SymfonyRequest(
            $query,
            $request,
            [],
            $cookie,
            $files,
            $server,
            $content
        );
    }



    public function run(): void
    {
        $this->bootstrap();
        self::$server->start();
    }

    public function sleep(int $millisecond): void
    {
        Coroutine::sleep($millisecond / 1000);
    }

    public function fail(): void
    {
        self::$server->shutdown();
    }

    public function closeClient(Conversation $conversation): void
    {
    }


}
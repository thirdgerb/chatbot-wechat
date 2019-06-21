<?php

/**
 * Class WechatServerCommand
 * @package Commune\Chatbot\Wechat\Laravel\Commands
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\Commands;


use Commune\Chatbot\Contracts\ChatServer;
use Commune\Chatbot\Framework\ChatApp;
use Commune\Chatbot\Wechat\OfficialAccount\SwooleOfficialAccountServer;
use Commune\Container\IlluminateAdapter;
use Illuminate\Console\Command;

class WechatServerCommand extends Command
{
    protected $signature = 'commune:wechat-server';

    protected $description = 'commune chatbot wechat server based on swoole';


    public function handle()
    {
        $app = $this->getLaravel();
        $config = $app['config']['commune']['chatbot'];

        $chatApp = new ChatApp(
            $config,
            new IlluminateAdapter($app)
        );

        $chatApp
            ->getReactorContainer()
            ->singleton(
                ChatServer::class,
                SwooleOfficialAccountServer::class
            );

        $chatApp->getServer()->run();
    }


}
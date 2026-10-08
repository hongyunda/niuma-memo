<?php
namespace app\command;

use Minishlink\WebPush\VAPID;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class PushVapid extends Command
{
    protected function configure()
    {
        $this->setName('push:vapid')->setDescription('生成 Web Push 的 VAPID 密钥对，填到 .env');
    }

    protected function execute(Input $input, Output $output)
    {
        $keys = VAPID::createVapidKeys();
        $output->writeln('把下面两行写入 .env：');
        $output->writeln('VAPID_PUBLIC = ' . $keys['publicKey']);
        $output->writeln('VAPID_PRIVATE = ' . $keys['privateKey']);
        return 0;
    }
}

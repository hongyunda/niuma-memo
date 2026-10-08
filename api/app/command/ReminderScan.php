<?php
namespace app\command;

use app\service\ReminderService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * crontab: * * * * * cd /www/memo/api && php think reminder:scan >> runtime/reminder.log 2>&1
 */
class ReminderScan extends Command
{
    protected function configure()
    {
        $this->setName('reminder:scan')->setDescription('扫描到期提醒并推送');
    }

    protected function execute(Input $input, Output $output)
    {
        $lockFile = runtime_path() . 'reminder.lock';
        $fp = fopen($lockFile, 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] 上一轮仍在执行，跳过');
            return 0;
        }
        try {
            $count = (new ReminderService())->scanDue();
            if ($count > 0) {
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 已推送 ' . $count . ' 条提醒');
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return 0;
    }
}

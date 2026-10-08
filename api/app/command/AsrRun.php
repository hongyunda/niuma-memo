<?php
namespace app\command;

use app\model\Attachment;
use app\service\AsrService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * crontab: * * * * * cd /www/memo/api && php think asr:run >> runtime/asr.log 2>&1
 * 兜底处理用户关掉页面后仍在排队 / 转写中的音频
 */
class AsrRun extends Command
{
    protected function configure()
    {
        $this->setName('asr:run')->setDescription('处理排队中的语音转文字任务');
    }

    protected function execute(Input $input, Output $output)
    {
        if (!AsrService::enabled()) {
            return 0;
        }
        $svc  = new AsrService();
        $list = Attachment::whereIn('asr_status', [Attachment::ASR_QUEUED, Attachment::ASR_PROCESSING])
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 20))
            ->order('id', 'asc')->limit(20)->select();
        foreach ($list as $att) {
            $att = $svc->process($att);
            $output->writeln(sprintf('[%s] #%d %s -> %d %s', date('H:i:s'), $att->id, $att->original_name, $att->asr_status, $att->asr_error));
        }
        return 0;
    }
}

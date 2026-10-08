<?php
namespace app\command;

use app\model\Note;
use app\service\NoteTitleService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class NoteTitlesRun extends Command
{
    protected function configure()
    {
        $this->setName('notes:titles')->setDescription('生成排队中的 AI 标题');
    }

    protected function execute(Input $input, Output $output)
    {
        if (!NoteTitleService::enabled()) return 0;
        $svc = new NoteTitleService();
        $svc->recover();
        foreach (Note::where('title_status', 'pending')->order('id')->limit(20)->select() as $note) {
            $svc->process((int) $note->id);
            $output->writeln('#' . $note->id . ' 已处理');
        }
        return 0;
    }
}

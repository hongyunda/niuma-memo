<?php
namespace app\command;

use app\model\Attachment;
use app\service\AttachmentService;
use app\service\CosStorage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Throwable;

class AttachmentsToCos extends Command
{
    protected function configure()
    {
        $this->setName('attachments:to-cos')->setDescription('迁移本地附件及缩略图到 COS（保留本地备份，可重复运行）');
    }

    protected function execute(Input $input, Output $output)
    {
        if (!CosStorage::configured()) {
            $output->writeln('<error>请先配置 COS_BUCKET、COS_REGION、COS_SECRET_ID、COS_SECRET_KEY</error>');
            return 1;
        }
        $files = new AttachmentService();
        $failed = 0;
        $cursor = 0;
        do {
            $list = Attachment::where('storage', 'local')->where('id', '>', $cursor)->order('id')->limit(50)->select();
            foreach ($list as $att) {
                $cursor = (int) $att->id;
                try {
                    $files->moveToCos($att);
                    $output->writeln('#' . $att->id . ' 已迁移');
                } catch (Throwable $e) {
                    $failed++;
                    $output->writeln('<error>#' . $att->id . ' 迁移失败，原文件已保留</error>');
                }
            }
        } while (count($list) === 50);
        return $failed ? 1 : 0;
    }
}

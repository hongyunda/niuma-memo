<?php
namespace app\command;

use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * php think user:create admin 'YourPassword' --nickname=老板
 * php think user:create admin 'NewPassword' --reset   重置密码
 */
class UserCreate extends Command
{
    protected function configure()
    {
        $this->setName('user:create')
            ->addArgument('username', Argument::REQUIRED, '用户名')
            ->addArgument('password', Argument::REQUIRED, '密码（至少 6 位，建议 12 位以上）')
            ->addOption('nickname', null, Option::VALUE_OPTIONAL, '昵称', '')
            ->addOption('reset', null, Option::VALUE_NONE, '用户已存在时重置密码')
            ->setDescription('创建登录用户 / 重置密码');
    }

    protected function execute(Input $input, Output $output)
    {
        $username = trim((string) $input->getArgument('username'));
        $password = (string) $input->getArgument('password');
        if (mb_strlen($password) < 6) {
            $output->error('密码至少 6 位');
            return 1;
        }
        $user = User::where('username', $username)->find();
        if ($user) {
            if (!$input->getOption('reset')) {
                $output->error("用户 {$username} 已存在，加 --reset 可重置密码");
                return 1;
            }
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
            $output->info("已重置 {$username} 的密码");
            return 0;
        }
        User::create([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => (string) $input->getOption('nickname') ?: $username,
        ]);
        $output->info("用户 {$username} 创建成功");
        return 0;
    }
}

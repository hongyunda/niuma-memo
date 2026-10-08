<?php
use app\middleware\Auth;
use think\facade\Route;

Route::group('api', function () {
    Route::get('health', function () {
        return json(['code' => 0, 'msg' => 'ok', 'data' => ['time' => date('Y-m-d H:i:s')]]);
    });

    // 无需登录
    Route::post('auth/login', 'api.Auth/login');
    // 私有文件：控制器内部校验 登录态 或 临时签名
    Route::rule('files/:id/thumb', 'api.File/thumb', 'GET|HEAD');
    Route::rule('files/:id', 'api.File/show', 'GET|HEAD');

    // 需要登录
    Route::group(function () {
        Route::get('auth/me', 'api.Auth/me');
        Route::post('auth/logout', 'api.Auth/logout');
        Route::put('auth/password', 'api.Auth/password');

        Route::get('dashboard', 'api.Dashboard/index');
        Route::get('search', 'api.Search/index');

        // 项目
        Route::get('projects', 'api.Project/index');
        Route::post('projects', 'api.Project/save');
        Route::put('projects/sort', 'api.Project/sort');
        Route::get('projects/:id/resources', 'api.Project/resources');
        Route::get('projects/:id', 'api.Project/read');
        Route::put('projects/:id', 'api.Project/update');
        Route::delete('projects/:id', 'api.Project/delete');

        // 记录
        Route::get('notes', 'api.Note/index');
        Route::post('notes', 'api.Note/save');
        Route::get('notes/trash', 'api.Note/trash');
        Route::put('notes/move', 'api.Note/move');
        Route::get('notes/:id', 'api.Note/read');
        Route::put('notes/:id', 'api.Note/update');
        Route::delete('notes/:id', 'api.Note/delete');
        Route::put('notes/:id/pin', 'api.Note/pin');
        Route::put('notes/:id/archive', 'api.Note/archive');
        Route::post('notes/:id/title', 'api.Note/title');
        Route::put('notes/:id/restore', 'api.Note/restore');
        Route::delete('notes/:id/force', 'api.Note/force');

        // 附件
        Route::post('attachments', 'api.Attachment/upload');
        Route::put('attachments/:id', 'api.Attachment/update');
        Route::delete('attachments/:id', 'api.Attachment/delete');
        Route::post('attachments/:id/transcribe', 'api.Attachment/transcribe');

        // 标签
        Route::get('tags', 'api.Tag/index');
        Route::post('tags', 'api.Tag/save');
        Route::put('tags/:id', 'api.Tag/update');
        Route::delete('tags/:id', 'api.Tag/delete');

        // 提醒
        Route::get('reminders', 'api.Reminder/index');
        Route::get('reminders/calendar', 'api.Reminder/calendar');
        Route::post('reminders', 'api.Reminder/save');
        Route::put('reminders/:id', 'api.Reminder/update');
        Route::delete('reminders/:id', 'api.Reminder/delete');
        Route::put('reminders/:id/snooze', 'api.Reminder/snooze');
        Route::put('reminders/:id/done', 'api.Reminder/done');

        // 推送 & 设置
        Route::post('push/subscribe', 'api.Push/subscribe');
        Route::delete('push/subscribe', 'api.Push/unsubscribe');
        Route::post('push/test', 'api.Push/test');
        Route::get('settings', 'api.Setting/index');
        Route::put('settings/push', 'api.Setting/push');
    })->middleware(Auth::class);
})->pattern(['id' => '\d+']);

// /api 下未匹配的路径统一 404 JSON
Route::miss(function () {
    $path = ltrim((string) request()->pathinfo(), '/');
    if (str_starts_with($path, 'api')) {
        return json(['code' => 404, 'msg' => '接口不存在'], 404);
    }
    abort(404);
});

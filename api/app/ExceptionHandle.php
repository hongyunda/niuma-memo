<?php
namespace app;

use app\exception\ApiException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类：/api 下的请求统一返回 JSON
 */
class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        ApiException::class,
    ];

    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    public function render($request, Throwable $e): Response
    {
        if (!$this->isApi($request)) {
            return parent::render($request, $e);
        }

        if ($e instanceof ApiException) {
            $code = $e->getCode() ?: 400;
            return json(['code' => $code, 'msg' => $e->getMessage()], ($code >= 400 && $code < 600) ? $code : 400);
        }
        if ($e instanceof ValidateException) {
            $error = $e->getError();
            return json(['code' => 422, 'msg' => is_array($error) ? implode('；', $error) : (string) $error], 422);
        }
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return json(['code' => 404, 'msg' => '数据不存在或已被删除'], 404);
        }
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $msg    = $e->getMessage() ?: ($status === 404 ? '接口不存在' : '请求错误');
            return json(['code' => $status, 'msg' => $msg], $status);
        }
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        $debug = $this->app->isDebug();
        return json([
            'code'  => 500,
            'msg'   => $debug ? $e->getMessage() : '服务器开小差了，请稍后再试',
            'debug' => $debug ? ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12)] : null,
        ], 500);
    }

    protected function isApi($request): bool
    {
        $path = ltrim((string) $request->pathinfo(), '/');
        return str_starts_with($path, 'api/') || $path === 'api' || $request->isJson();
    }
}

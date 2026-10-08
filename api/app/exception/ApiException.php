<?php
namespace app\exception;

use RuntimeException;

/**
 * 业务异常：code 为 HTTP 状态码（400/401/403/404/409/422/429），msg 直接展示给用户
 */
class ApiException extends RuntimeException
{
    public function __construct(string $message = '操作失败', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}

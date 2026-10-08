<?php
namespace app\controller\api;

use app\BaseController;
use app\exception\ApiException;
use think\Response;

abstract class Base extends BaseController
{
    protected function success($data = null, string $msg = 'ok'): Response
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    protected function fail(string $msg, int $code = 400): Response
    {
        throw new ApiException($msg, $code);
    }

    protected function uid(): int
    {
        return (int) $this->request->userId;
    }

    /** @return array{0:int,1:int} [page, limit] */
    protected function paging(int $defaultLimit = 20): array
    {
        $page  = max(1, (int) $this->request->get('page', 1));
        $limit = min(100, max(1, (int) $this->request->get('limit', $defaultLimit)));
        return [$page, $limit];
    }

    protected function paginate($paginator): array
    {
        $arr = $paginator->toArray();
        return [
            'list'      => $arr['data'],
            'total'     => $arr['total'],
            'page'      => $arr['current_page'],
            'limit'     => $arr['per_page'],
            'last_page' => $arr['last_page'],
        ];
    }
}

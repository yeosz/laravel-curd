<?php

namespace Yeosz\LaravelCurd\Traits;

trait ResponseTrait
{
    /**
     * 返回错误信息
     *
     * @param int $code 错误代码
     * @param string $message 错误信息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseError($code, $message)
    {
        return $this->responseMessage($code, $message);
    }

    /**
     * 返回成功信息
     *
     * @param string $message 要返回的文本信息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseSuccess($message = '操作成功')
    {
        return $this->responseMessage(200, $message);
    }

    /**
     * 返回数据
     *
     * @param string|int|double $data 要返回的数据
     * @param string $message 要返回的提示信息
     * @return \Illuminate\Http\JsonResponse
     */
    protected function responseData($data, $message = '操作成功')
    {
        $data = ['code' => 200, 'message' => $message, 'data' => $data];
        return $this->createResponse($data);
    }

    /**
     * 组装响应数据
     * @param int $code 错误代码
     * @param string $message 错误消息
     * @return \Illuminate\Http\JsonResponse
     */
    private function responseMessage($code, $message)
    {
        $data = ['code' => $code, 'message' => $message];
        return $this->createResponse($data);
    }

    /**
     * 生成响应数据
     *
     * @param array $data 响应数据
     * @return \Illuminate\Http\JsonResponse
     */
    private function createResponse($data)
    {
        //设置不缓存
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Cookie, Accept',
            'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, OPTIONS',
            //'Access-Control-Allow-Credentials' => 'true',
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Pragma' => 'no-cache',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT'
        ];

        return response()->json($data, 200, $headers);
    }
}
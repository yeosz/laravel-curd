<?php

namespace Yeosz\LaravelCurd;

use \Exception;

class ApiException extends Exception
{
    /**
     * @var int 成功（无错误）
     */
    const ERROR_OK = 200;

    /**
     * @var int 错误号：未知错误
     */
    const ERROR_UNKNOWN = 4000;

    /**
     * @var int 错误号：未登录或登录状态已超时
     */
    const ERROR_NOT_LOGIN = 4001;

    /**
     * @var int 错误号：认证失败
     */
    const ERROR_AUTH_FAILED = 4002;

    /**
     * @var int 错误号：数据验证失败
     */
    const ERROR_VALIDATION_FAILED = 4003;

    /**
     * @var int 错误号：登录失败
     */
    const ERROR_LOGIN_FAILED = 4004;

    /**
     * @var int 错误号：令牌过期
     */
    const ERROR_TOKEN_EXPIRE = 4005;

    /**
     * @var int 错误号：令牌无效
     */
    const ERROR_TOKEN_INVALID = 4006;

    /**
     * @var int 错误号：令牌数据错误
     */
    const ERROR_TOKEN_BAD = 4007;

    /**
     * @var int 错误号：没有权限
     */
    const ERROR_NO_PERMIT = 4008;

    /**
     * @var int 错误号：令牌已被黑名单
     */
    const ERROR_TOKEN_BLACKLISTED = 4009;

    /**
     * @var int 错误号：对象不存在
     */
    const ERROR_NOT_FOUND = 4010;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var int
     */
    protected $code;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * CommonException constructor.
     *
     * ApiException constructor.
     * @param string $message 异常信息
     * @param int $errorCode 异常CODE
     * @param null $data 异常返回的数据
     */
    public function __construct($message, $errorCode = self::ERROR_UNKNOWN, $data = null)
    {
        $this->code = $errorCode;
        $this->message = $message;
        $this->data = $data;
        parent::__construct($message, $errorCode);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render()
    {
        if (is_null($this->data)) {
            return response()->json(['code' => $this->code, 'message' => $this->message]);
        } else {
            return response()->json(['code' => $this->code, 'message' => $this->message, 'data' => $this->data]);
        }
    }
}
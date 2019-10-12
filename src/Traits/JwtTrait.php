<?php
/**
 * jwt控制器及中间件
 *
 * @link https://learnku.com/articles/10885/full-use-of-jwt
 *
 */

namespace Yeosz\LaravelCurd\Traits;

use Yeosz\LaravelCurd\ApiException;
use Illuminate\Http\Request;
use Auth;
use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use JWTAuth;

/**
 * Class JwtTrait
 *
 * @desc
 * 安装jwt,配置config/auth文件,配置模型
 * 1.控制器用法
 * 定义属性$issuer,$guard,$credentials
 * 可重写checkLoginUser方法,增加验证逻辑,或者重写login方法
 * 2.中间件用法
 * 定义属性$issuer,handle方法代码:return $this->jwtHandle($request, $next);
 * 添加中间件到app/Http/Kernel.php
 *
 * @package Yeosz\LaravelCurd\Traits
 */
trait JwtTrait
{
    use ResponseTrait;

//    /**
//     * @var string
//     */
//    protected $issuer = 'api.login';

//    /**
//     * @var string
//     */
//    protected $guard = 'api'; // config下auth中定义的guards

//    /**
//     * @var array
//     */
//    protected $credentials = ['username', 'password']; // 登录请求的参数

    /**
     * Get a JWT token via given credentials.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only($this->getCredentials());
        $guard = $this->guard();
        $token = $guard->attempt($credentials, true);
        if ($token) {
            try {
                $this->checkLoginUser($guard->user());
                return $this->responseData($token);
            } catch (\Exception $e) {
                return $this->responseError($e->getCode(), $e->getMessage());
            }
        }

        return $this->responseError(4000, 'Unauthorized');
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return $this->responseSuccess();
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $this->checkLoginUser($this->guard()->user());
            return $this->responseData($this->guard()->refresh());
        } catch (\Exception $e) {
            return $this->responseError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 个人信息
     *
     * @return array
     */
    public function profile()
    {
        return $this->responseData($this->guard()->user());
    }

    /**
     * Handle an incoming request.
     * 中间件handle方法
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function jwtHandle($request, Closure $next)
    {
        try {
            $issuer = $this->getIssuer();
            if (!JWTAuth::parseToken()->check()) {
                return $this->responseError(ApiException::ERROR_NOT_LOGIN, '尚未登录或登录状态已超时');
            } else {
                $payload = JWTAuth::getPayload()->toArray();
                $checked = is_array($issuer) ? in_array($payload['iss'], $issuer) : $payload['iss'] == $issuer;
                if (!$checked) {
                    return $this->responseError(ApiException::ERROR_UNKNOWN, '令牌无效');
                }
            }
        } catch (TokenExpiredException $e) {
            return $this->responseError(ApiException::ERROR_TOKEN_EXPIRE, '令牌已过期');
        } catch (TokenInvalidException $e) {
            return $this->responseError(ApiException::ERROR_TOKEN_INVALID, '令牌无效');
        } catch (JWTException $e) {
            return $this->responseError(ApiException::ERROR_TOKEN_BAD, '令牌错误');
        }

        return $next($request);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Tymon\JWTAuth\JWTGuard
     */
    protected function guard()
    {
        return Auth::guard($this->getAuthGuard())->claims(['iss' => $this->getIssuer()]);
    }

    /**
     * 验证用户
     *
     * @param $user
     * @return bool
     * @throws \Exception
     */
    protected function checkLoginUser($user)
    {
        return true;
    }

    /**
     * jwt token签发人
     *
     * @return string
     */
    protected function getIssuer()
    {
        return empty($this->issuer) ? 'api' : $this->issuer;
    }

    /**
     * guard
     *
     * @return string
     */
    protected function getAuthGuard()
    {
        return empty($this->guard) ? 'api' : $this->guard;
    }

    /**
     *
     *
     * @return array
     */
    protected function getCredentials()
    {
        return empty($this->credentials) ? ['username', 'password'] : $this->credentials;
    }
}
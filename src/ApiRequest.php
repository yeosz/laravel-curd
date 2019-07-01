<?php

namespace Yeosz\LaravelCurd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ApiRequest extends FormRequest
{
    /**
     * 过滤
     * 一维数组,或路由名作key多维数组,或者混合数组
     *
     * @var array
     */
    public $only = [];

    /**
     * 纠正的配置
     *
     * @var array
     */
    protected $correct = [
//        ['sort', 'intval'], // 整形
//        ['price', 'floatval'], // 浮点型处理
//        ['test', 'implode'], // 逗号连接
//        ['empty', 'unset'], // 空值就删除
//        ['password', 'password'], // bcrypt
//        ['alpha', 'strtolower'], // 转小写
//        ['alpha', 'strtoupper'], // 转大写
//        ['alpha', 'correctTest'], // 自定义处理方法,方法名必须correct开头
    ];

    /**
     * 验证失败
     *
     * @param Validator $validator
     * @throws ApiException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        throw new ApiException('验证失败', ApiException::ERROR_VALIDATION_FAILED, $errors);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * 纠正数据
     *
     * @param array|null $arr
     * @return array
     */
    public function correct($arr = null)
    {
        $routeName = $this->route()->getName();

        if (is_null($arr)) {
            if (!empty($routeName) && isset($this->only[$routeName])) {
                $only = $this->only[$routeName];
            } else {
                $only = array_filter($this->only, 'is_string');
            }
            $arr = empty($only) ? $this->all() : $this->only($only);
        }

        foreach ($this->correct as $value) {
            list($key, $item) = $value;
            if (!isset($arr[$key])) {
                continue;
            }
            if ($item == 'intval') {
                $arr[$key] = intval($arr[$key]);
            } elseif ($item == 'floatval') {
                $arr[$key] = floatval($arr[$key]);
            } elseif ($item == 'password') {
                $arr[$key] = bcrypt($arr[$key]);
            } elseif ($item == 'implode' && is_array($arr[$key])) {
                $arr[$key] = implode(',', $arr[$key]);
            } elseif ($item == 'unset' && empty($arr[$key])) {
                unset($arr[$key]);
            } elseif ($item == 'strtolower' && is_string($arr[$key])) {
                $arr[$key] = strtolower($arr[$key]);
            } elseif ($item == 'strtoupper' && is_string($arr[$key])) {
                $arr[$key] = strtoupper($arr[$key]);
            } elseif (stripos($item, 'correct') === 0) {
                $arr[$key] = $this->$item($arr[$key], $arr);
            }
        }
        return $arr;
    }

    /**
     * 转换规则,兼容X-editable组件
     *
     * @param $rules
     * @return array
     */
    public function xEditableRules($rules)
    {
        if ($this->has(['pk', 'name', 'value'])) {
            return [
                'value' => $rules[$this->name]
            ];
        } else {
            return $rules;
        }
    }
}

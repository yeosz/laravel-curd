<?php

namespace App\Http\Controllers;

use Yeosz\LaravelCurd\Traits\CurdTrait;
use Yeosz\LaravelCurd\Traits\TreeTrait;

class CommonController extends BaseController
{
    use CurdTrait,TreeTrait;

    /**
     * 模型
     *
     * @var string
     */
    protected $model = '';

    /**
     * 模板
     *
     * @var array
     */
    protected $view = [
        'create' => '',
        'edit' => '',
    ];
}

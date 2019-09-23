<?php
/**
 * 增删改的通用方法
 *
 */

namespace Yeosz\LaravelCurd\Traits;

use Yeosz\LaravelCurd\ApiException;
use Illuminate\Support\Facades\View as XView;

trait CurdTrait
{
    use ResponseTrait;

//    // 以下定义到Controller中
//    /**
//     * 模型
//     *
//     * @var string
//     */
//    protected static $model = '';
//
//    /**
//     * @var string auth guard 获取用户 appendOperator方法使用
//     */
//    protected $guard = '';
//
//    /**
//     * 模板
//     *
//     * @var array
//     */
//    protected static $view = [
//        'add' => '',
//        'edit' => '',
//    ];

    /**
     *
     *
     * @var array
     */
    private $xAssign = [];

    /**
     * 新增页
     *
     * @return \Illuminate\View\View
     * @throws \Exception
     */
    protected function xCreate()
    {
        if (empty($this->view['create'])) {
            throw new \Exception('请配置模板', 404);
        } else {
            return $this->xView($this->view['create']);
        }
    }

    /**
     * 保存
     *
     * @param $request
     * @param \Closure|null $closure
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xStore($request, \Closure $closure = null)
    {
        if ($request instanceof \Illuminate\Http\Request) {
            $new = method_exists($request, 'correct') ? $request->correct() : $request->all();
        } else {
            $new = $request;
        }

        $new = $this->appendOperator('create', $new);

        if (!is_null($closure)) {
            \DB::beginTransaction();
            try {
                $row = $this->getModel()->create($new);
                $closure($row);
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                return $this->responseError(ApiException::ERROR_UNKNOWN, $e->getMessage());
            }
        } else {
            $row = $this->getModel()->create($new);
        }

        $id = empty($row->id) ? 0 : $row->id;
        return $this->responseData($id);
    }

    /**
     * 编辑页
     *
     * @param int $id
     * @param array $loads
     * @param array $attributes
     * @return \Illuminate\View\View
     * @throws \Exception
     */
    protected function xEdit($id, $loads = [], $attributes = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new \Exception('数据不存在', 404);
        }
        if (empty($this->view['edit'])) {
            throw new \Exception('请配置模板', 404);
        }

        foreach ($loads as $load) {
            $row->load($load);
        }
        foreach ($attributes as $attribute) {
            $row->setAttribute($attribute, $row->$attribute);
        }

        return $this->xAssign('row', $row)->xView($this->view['edit']);
    }

    /**
     * 详情接口
     *
     * @param int $id
     * @param array $loads
     * @param array $attributes
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xShow($id, $loads = [], $attributes = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }

        foreach ($loads as $load) {
            $row->load($load);
        }
        foreach ($attributes as $attribute) {
            $row->setAttribute($attribute, $row->$attribute);
        }

        return $this->responseData($row);
    }

    /**
     * 修改的接口
     *
     * @param $id
     * @param $new
     * @param \Closure|null $closure
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xUpdate($id, $new, \Closure $closure = null)
    {
        $row = $this->getModel()->find($id);
        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }

        if ($new instanceof \Illuminate\Http\Request) {
            $new = method_exists($new, 'correct') ? $new->correct() : $new->all();
        }

        if (!empty($new)) {
            $new = $this->appendOperator('update', $new);

            if (!is_null($closure)) {
                \DB::beginTransaction();
                try {
                    $row->update($new);
                    $closure($row);
                    \DB::commit();
                } catch (\Exception $e) {
                    \DB::rollBack();
                    return $this->responseError(ApiException::ERROR_UNKNOWN, $e->getMessage());
                }
            } else {
                $row->update($new);
            }
        }

        return $this->responseSuccess();
    }

    /**
     * 修改列
     *
     * @param int $id
     * @param array|\Illuminate\Http\Request $new 待保存的内容
     * @param array $valueIn 取值范围
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xUpdateColumn($id, $new, $valueIn = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }
        if ($new instanceof \Illuminate\Http\Request) {
            $new = $new->all();
        }
        if (!is_array($new) || empty($new)) {
            throw new ApiException('参数异常', ApiException::ERROR_NOT_FOUND);
        }
        if (!empty($new['name']) && isset($new['value'])) {
            $column = $new['name'];
            $value = $new['value'];
        } else {
            $column = key($new);
            $value = current($new);
        }

        if ($valueIn && !in_array($value, $valueIn)) {
            throw new ApiException('参数不合法', ApiException::ERROR_NOT_FOUND);
        }

        $new = $this->appendOperator('update', [$column => $value]);

        try {
            $row->update($new);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        return $this->responseSuccess('修改成功');
    }

    /**
     * 批量修改列
     *
     * @param string $column 修改的列
     * @param array $valueIn 取值范围
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function xBatchUpdateColumn($column, $valueIn = [])
    {
        $request = request();
        $ids = $this->getRequestParamIds($request, true);

        $newValue = $request->input($column, '');

        if ($valueIn && !in_array($newValue, $valueIn)) {
            throw new ApiException('参数错误');
        }

        $new = $this->appendOperator('update', [$column => $newValue]);
        $count = $this->getModel()->whereIn('id', $ids)->update($new);

        return $this->responseData($count);
    }

    /**
     * 列值切换
     *
     * @param int $id
     * @param string $column
     * @param array $values
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xToggleColumn($id, $column, $values = [1, 2])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }
        if (count($values) != 2) {
            throw new ApiException('参数异常');
        }

        if ($row->$column == current($values)) {
            $newValue = end($values);
        } else {
            $newValue = current($values);
        }

        $new = $this->appendOperator('update', [$column => $newValue]);
        $row->update($new);

        return $this->responseData($newValue);
    }

    /**
     * 删除
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xDelete($id)
    {
        $count = $this->xExecuteDelete([$id]);

        return $this->responseData($count, '删除成功');
    }

    /**
     * 批量删除
     *
     * @param array|\Illuminate\Http\Request $ids
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xBatchDelete($ids)
    {
        if ($ids instanceof \Illuminate\Http\Request) {
            $ids = $this->getRequestParamIds($ids, true);
        }

        $count = $this->xExecuteDelete($ids);

        return $this->responseData($count);
    }

    /**
     * 执行删除操作
     *
     * @param array $ids
     * @return bool
     * @throws \Exception
     */
    protected function xExecuteDelete($ids = [])
    {
        $model = $this->getModel();
        if (property_exists($model, 'forceDeleting')) {
            // 软删除
            $new = [
                $model::getModel()->getDeletedAtColumn() => $model->getModel()->freshTimestampString(),
            ];
            $new = $this->appendOperator('delete', $new);
            return $model->whereIn('id', $ids)->update($new);
        } else {
            return $this->getModel()->whereIn('id', $ids)->delete();
        }
    }

    /**
     * 上传文件
     *
     * @param string $dir 保存的目录
     * @param string $url url前缀
     * @param string $input input name
     * @param array $extensions 扩展名类型
     * @param bool $originalName 原始文件名
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xUploadFile($dir, $url, $input, $extensions = [], $originalName = false)
    {
        $request = request();
        // 检查文件
        if (!$request->files->has($input)) {
            throw new ApiException('未上传文件');
        }
        $file = $request->file($input);
        if (is_array($file)) {
            $file = current($file);
        }

        $ext = $file->getClientOriginalExtension();
        $ext = strtolower($ext);
        if (!empty($extensions) && !in_array($ext, $extensions)) {
            throw new ApiException('文件类型不合法');
        }

        if ($originalName) {
            $filename = $file->getClientOriginalName();
        } else {
            $filename = date('His') . mt_rand(1111, 9999) . '.' . $ext;
        }
        $url = $url . '/' . $filename;
        $filePath = $dir . '/' . $filename;
        if (file_exists($filePath)) {
            if (md5_file($filePath) == md5_file($file->path())) {
                return $this->responseData($url);
            }
            throw new ApiException('文件名已经存在');
        }
        try {
            $file->move($dir, $filename);
            return $this->responseData($url);
        } catch (\Exception $e) {
            throw new ApiException('上传失败');
        }
    }

    /**
     * 视图
     *
     * @param string $template
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    protected function xView(string $template, array $data = [], array $mergeData = [])
    {
        if ($data) {
            $this->xAssign = array_merge($this->xAssign, $data);
        }
        if ($mergeData) {
            $this->xAssign = array_merge($this->xAssign, $mergeData);
        }
        return view($template, $this->xAssign);
    }

    /**
     * 赋值
     *
     * @param null $key
     * @param null $value
     * @param null $share
     * @return $this
     * @throws \Exception
     */
    protected function xAssign($key = null, $value = null, $share = null)
    {
        if (is_string($key) && $share === true) {
            XView::share($key, $value);
        } elseif (is_string($key)) {
            $this->xAssign[$key] = $value;
        } elseif (is_array($key) && is_null($value) && empty($share)) {
            $this->xAssign = array_merge($this->xAssign, $key);
        } else {
            throw new \Exception('参数异常');
        }
        return $this;
    }

    /**
     * 获取模型
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    protected function getModel()
    {
        if (empty($this->model)) {
            throw new \Exception('model 未定义');
        } else if (is_string($this->model)) {
            $this->model = new $this->model;
        }
        return $this->model;
    }

    /**
     * 获取request ids
     *
     * @param \Illuminate\Http\Request $request
     * @param bool $check
     * @param string $param
     * @return array
     * @throws \Exception
     */
    protected function getRequestParamIds($request, $check = true, $param = 'ids')
    {
        $ids = $request->input($param, '');
        if (empty($ids)) {
            $ids = [];
        } elseif (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_filter($ids, 'is_numeric');
        if (!$ids && $check) {
            throw new \Exception('参数错误');
        }
        return $ids;
    }

    /**
     * 追加操作人
     *
     * @param $action
     * @param $new
     * @return mixed
     * @throws \Exception
     */
    protected function appendOperator($action, $new)
    {
        $guard = empty($this->guard) ? '' : $this->guard;
        $uid = request()->user($guard) ? request()->user($guard)->id : 0;
        if (!$uid) {
            return $new;
        }
        $model = $this->getModel();
        $class = get_class($model);
        switch ($action) {
            case 'create':
                if (defined($class . '::CREATED_BY')) {
                    $new[$class::CREATED_BY] = $uid;
                }
                break;
            case 'update':
                if (defined($class . '::UPDATED_BY')) {
                    $new[$class::UPDATED_BY] = $uid;
                }
                break;
            case 'delete':
                if (defined($class . '::DELETED_BY')) {
                    $new[$class::DELETED_BY] = $uid;
                }
                break;
        }
        return $new;
    }
}
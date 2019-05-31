<?php
/**
 * 增删改的通用方法
 *
 */

namespace Yeosz\LaravelCurd\Traits;

use Yeosz\LaravelCurd\ApiException;

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
    public $assign = [];

    /**
     * 新增页
     *
     * @return \Illuminate\View\View
     * @throws ApiException
     */
    protected function xCreate()
    {
        if (empty($this->view['create'])) {
            throw new ApiException('请配置模板');
        } else {
            return view($this->view['create'], $this->assign);
        }
    }

    /**
     * 保存
     *
     * @param \Illuminate\Http\Request|array $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function xStore($request)
    {
        if ($request instanceof \Illuminate\Http\Request) {
            $new = method_exists($request, 'correct') ? $request->correct() : $request->all();
        } else {
            $new = $request;
        }

        $row = $this->getModel()->create($new);
        $id = empty($row->id) ? 0 : $row->id;

        return $this->responseData($id);
    }

    /**
     * 编辑页
     *
     * @param int $id
     * @param array $loads
     * @return \Illuminate\View\View
     * @throws ApiException
     */
    protected function xEdit($id, $loads = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }
        if (empty($this->view['edit'])) {
            throw new ApiException('请配置模板');
        }
        foreach ($loads as $load) {
            $row->load($load);
        }

        $this->assign['row'] = $row;

        return view($this->view['edit'], $this->assign);
    }

    /**
     * 详情接口
     *
     * @param int $id
     * @param array $loads
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xShow($id, $loads = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }
        foreach ($loads as $load) {
            $row->load($load);
        }

        return $this->responseData($row);
    }

    /**
     * 修改的接口
     *
     * @param int $id
     * @param \Illuminate\Http\Request|array $new
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xUpdate($id, $new)
    {
        $row = $this->getModel()->find($id);
        if (!$row) {
            throw new ApiException('数据不存在', ApiException::ERROR_NOT_FOUND);
        }

        if ($new instanceof \Illuminate\Http\Request) {
            $new = method_exists($new, 'correct') ? $new->correct() : $new->all();
        }

        if (!empty($new)) {
            $row->update($new);
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
        if (!empty($new['name']) && !empty($new['value'])) {
            $column = $new['name'];
            $value = $new['value'];
        } else {
            $column = key($new);
            $value = current($new);
        }

        if ($valueIn && !in_array($value, $valueIn)) {
            throw new ApiException('参数不合法', ApiException::ERROR_NOT_FOUND);
        }

        try {
            $row->update([$column => $value]);
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

        $count = $this->getModel()->whereIn('id', $ids)->update([$column => $newValue]);

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
            $new = [$column => end($values)];
        } else {
            $new = [$column => current($values)];
        }

        $row->update($new);

        return $this->responseSuccess('修改成功');
    }

    /**
     * 删除
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xDelete($id)
    {
        $count = $this->getModel()->where('id', $id)->delete();

        return $this->responseData($count, '删除成功');
    }

    /**
     * 批量删除
     *
     * @param array|\Illuminate\Http\Request $ids
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xBatchDelete($ids)
    {
        if ($ids instanceof \Illuminate\Http\Request) {
            $ids = $this->getRequestParamIds($ids, true);
        }

        $count = $this->getModel()->whereIn('id', $ids)->delete();

        return $this->responseData($count);
    }

    /**
     * 上传文件
     *
     * @param string $dir 保存的目录
     * @param string $url url前缀
     * @param string $input input name
     * @param array $extensions 扩展名类型
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    protected function xUploadFile($dir, $url, $input, $extensions = [])
    {
        $request = request();
        // 检查文件
        if (!$request->files->has($input)) {
            throw new ApiException('未上传文件');
        }
        $file = $request->file($input);
        $ext = $file->getClientOriginalExtension();
        if (!empty($extensions) && !in_array($ext, $extensions)) {
            throw new ApiException('文件类型不合法');
        }

        $filename = date('His') . mt_rand(1111, 9999) . '.' . $ext;
        $path = $url . '/' . $filename;

        try {
            $file->move($dir, $filename);
            return $this->responseData($path);
        } catch (\Exception $e) {
            throw new ApiException('上传失败');
        }
    }

    /**
     * 获取模型
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws ApiException
     */
    protected function getModel()
    {
        if (empty($this->model)) {
            throw new ApiException('model 未定义', ApiException::ERROR_NOT_FOUND);
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
     * @return array
     * @throws ApiException
     */
    protected function getRequestParamIds($request, $check = true)
    {
        $ids = $request->input('ids', '');
        $ids = empty($ids) ? [] : explode(',', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!$ids && $check) {
            throw new ApiException('参数错误');
        }
        return $ids;
    }
}
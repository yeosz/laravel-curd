<?php
/**
 * 增删改的通用方法
 *
 */

namespace Yeosz\LaravelCurd\Traits;

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
     * @throws \Exception
     */
    protected function xCreate()
    {
        if (empty($this->view['create'])) {
            throw new \Exception('请配置模板', 4000);
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
     * @throws \Exception
     */
    protected function xEdit($id, $loads = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new \Exception('数据不存在', 4010);
        }
        if (empty($this->view['edit'])) {
            throw new \Exception('请配置模板', 4000);
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
     * @param $id
     * @param $loads
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xShow($id, $loads = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new \Exception('数据不存在', 4010);
        }
        foreach ($loads as $load) {
            $row->load($load);
        }

        return $this->responseData($row);
    }

    /**
     * 修改的接口
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xUpdate($id, $request)
    {
        $row = $this->getModel()->find($id);
        if (!$row) {
            throw new \Exception('数据不存在', 4010);
        }

        if ($request instanceof \Illuminate\Http\Request) {
            $new = method_exists($request, 'correct') ? $request->correct() : $request->all();
        } else {
            $new = $request;
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
     * @throws \Exception
     */
    protected function xUpdateColumn($id, $new, $valueIn = [])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new \Exception('数据不存在', 4010);
        }
        if ($new instanceof \Illuminate\Http\Request) {
            $new = $new->all();
        }
        if (!is_array($new) || empty($new)) {
            throw new \Exception('参数异常', 4010);
        }
        if (!empty($new['name']) && !empty($new['value'])) {
            $column = $new['name'];
            $value = $new['value'];
        } else {
            $column = key($new);
            $value = current($new);
        }

        if ($valueIn && !in_array($value, $valueIn)) {
            throw new \Exception('参数不合法', 4010);
        }

        try {
            $row->update([$column => $value]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 4000);
        }

        return $this->responseSuccess('修改成功');
    }

    /**
     * 批量修改列
     *
     * @param \Illuminate\Http\Request $request
     * @param string $column 修改的列
     * @param array $valueIn 取值范围
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function xBatchUpdateColumn(\Illuminate\Http\Request $request, $column, $valueIn = [])
    {
        $ids = $this->getRequestParamIds($request, true);

        $newValue = $request->input($column, '');

        if ($valueIn && !in_array($newValue, $valueIn)) {
            throw new \Exception('参数错误', 4000);
        }

        $count = $this->getModel()->whereIn('id', $ids)->update([$column => $newValue]);

        return $this->responseData($count);
    }

    /**
     * 列值切换
     *
     * @param $id
     * @param $column
     * @param array $values
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xToggleColumn($id, $column, $values = [1, 2])
    {
        $row = $this->getModel()->find($id);

        if (!$row) {
            throw new \Exception('数据不存在', 4010);
        }
        if (count($values) != 2) {
            throw new \Exception('参数异常', 4000);
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
     * @throws \Exception
     */
    protected function xDelete($id)
    {
        $count = $this->getModel()->where('id', $id)->delete();

        return $this->responseData($count, '删除成功');
    }

    /**
     * 批量删除
     *
     * @param array|\Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    protected function xBatchDelete($request)
    {
        if ($request instanceof \Illuminate\Http\Request) {
            $ids = $this->getRequestParamIds($request, true);
        } else {
            $ids = $request;
        }

        $count = $this->getModel()->whereIn('id', $ids)->delete();

        return $this->responseData($count);
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
            throw new \Exception('model 未定义', 4010);
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
     * @throws \Exception
     */
    protected function getRequestParamIds($request, $check = true)
    {
        $ids = $request->input('ids', '');
        $ids = empty($ids) ? [] : explode(',', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!$ids && $check) {
            throw new \Exception('参数错误',4000);
        }
        return $ids;
    }
}
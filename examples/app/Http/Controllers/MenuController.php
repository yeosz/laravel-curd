<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\CreateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MenuController extends CommonController
{
    /**
     * 模型
     *
     * @var string
     */
    protected $model = Menu::class;

    /**
     * 模板
     *
     * @var array
     */
    protected $view = [
        'create' => 'admin.menu.add',
        'edit' => 'admin.menu.edit',
    ];

    /**
     * 首页
     *
     * @return View
     */
    public function index()
    {
        $this->assign['tree'] = $this->getTreeList('parent_id', 'sort', $this->model);

        return view('admin.menu.index', $this->assign);
    }

    /**
     * 新增
     *
     * @param $pid
     * @return View
     */
    public function create($pid)
    {
        $this->assign['parent'] = $this->getModel()->find($pid);

        return parent::xCreate();
    }

    /**
     * 保存
     *
     * @param $pid
     * @param CreateMenuRequest $request
     * @return View|JsonResponse
     */
    public function store($pid, CreateMenuRequest $request)
    {
        $request->merge(['parent_id' => $pid]);

        return parent::xStore($request);
    }

    /**
     * 详情
     *
     * @param $id
     * @return View
     */
    public function show($id)
    {
        return parent::xEdit($id, ['parent']);
    }

    /**
     * 修改
     *
     * @param $id
     * @param CreateMenuRequest $request
     * @return JsonResponse
     */
    public function update($id, CreateMenuRequest $request)
    {
        return parent::xUpdate($id, $request);
    }

    /**
     * 删除
     *
     * @param $id
     * @return JsonResponse|void
     * @throws ApiException
     * @throws \Exception
     */
    public function delete($id)
    {
        $exists = $this->getModel()->where('parent_id', $id)->exists();

        if ($exists) {
            throw new ApiException('存在子菜单，不允许删除');
        }

        return parent::xDelete($id);
    }
}
<?php

namespace Yeosz\LaravelCurd;

use Maatwebsite\Excel\Concerns\Importable;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class Import
 *
 * @package Yeosz\LaravelCurd
 */
class Import
{
    use Importable;

    /**
     * 获取excel的数据
     *
     * @param string|UploadedFile|null $filePath 上传时$request->file('file')
     * @param int $sheet
     * @return array
     * @throws \Exception
     */
    public function getExcelData($filePath, $sheet = 0)
    {
        try {
            $sheets = $this->toArray($filePath);
        } catch (\Exception $e) {
            throw new \Exception('读取excel数据失败:' . $e->getMessage());
        }
        if (!isset($sheets[$sheet])) {
            throw new \Exception('工作表不存在');
        }
        $sheet = $sheets[$sheet];
        return $sheet;
    }
}

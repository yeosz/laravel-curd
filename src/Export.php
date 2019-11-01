<?php

namespace Yeosz\LaravelCurd;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Closure;

/**
 * Class Export
 * 依赖 maatwebsite/excel ^3.1
 *
 * @package Yeosz\LaravelCurd
 * @desctiption
 * 用法
 * (new Export)->setCollection($data)->setHeadings(['name1'=>'标题1','name2'=>'标题2'])->download('export.xlsx')
 */
class Export implements WithHeadings, WithEvents, FromArray
{
    use Exportable;

    protected $sorted = false;

    /**
     * @var Collection 数据
     */
    protected $collection;

    /**
     * @var array 标题
     */
    protected $excelHeadings = [];

    /**
     * @var array 原始标题
     */
    protected $rowHeadings = [];

    /**
     * @var array
     */
    protected $count = [
        'data' => 0,
        'title' => 0,
        'heading' => 0,
        'column' => 0,
    ];

    /**
     * @var Closure
     */
    protected $style;

    public function array(): array
    {
        $first = $this->collection->first();
        $object = is_object($first);

        // 需要隐藏的字段
        $hidden = [];
        if (is_object($first)) {
            try {
                $hidden = $first->getHidden();
            } catch (\Exception $e) {
                // pass
            }
        }

        if (!$this->sorted && !empty($this->rowHeadings)) {
            $rows = [];
            $this->collection->each(function ($item) use (&$rows, $object, $hidden) {
                $row = [];
                foreach ($this->rowHeadings as $key => $name) {
                    if (in_array($key, $hidden)) {
                        $row[] = '';
                    } else {
                        $row[] = $object ? ($item->{$key} ?? '') : ($item[$key] ?? '');
                    }
                }
                $rows[] = $row;
            });
            return $rows;
        } else {
            return $this->collection->all();
        }
    }

    public function headings(): array
    {
        return $this->excelHeadings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // $event->getDelegate()->setMergeCells(['A1:O1', 'A2:C2', 'D2:O2']); // 合并单元格
                // $event->sheet->getDelegate()->getStyle('A1:A10')->getAlignment()->setHorizontal('center'); // 设置居中
                if ($this->count['column']) {
                    // 设置边框
                    $rowCount = $this->count['data'] + $this->count['title'] + $this->count['heading'];
                    $columnCount = $this->count['column'] - 1;
                    $area = 'A' . strval($this->count['title'] + 1) . ':' . $this->getExcelTit($columnCount) . $rowCount;
                    $event->getDelegate()->getStyle($area)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => 'thin',
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ]
                    ]);
                }
                if (!is_null($this->style)) {
                    call_user_func($this->style, $event);
                }
            },
        ];
    }

    /**
     * 设置数据
     *
     * @param Collection $collection
     * @param bool $sorted 是否按标题排序的
     * @return $this
     */
    public function setCollection(Collection $collection, $sorted = false)
    {
        $this->collection = $collection;
        $this->count['data'] = $this->collection->count();
        $this->sorted = $sorted;
        return $this;
    }

    /**
     * 设置标题
     *
     * @param array $headings 列标题 一维数组  ['name1'=>'标题1','name2'=>'标题2']
     * @param array $titles excel标题 二维数组 [['A1单无格','B1单无格'],['A2单无格','B2单无格']]
     * @return $this
     */
    public function setHeadings(array $headings = [], array $titles = [])
    {
        $this->count['title'] = count($titles);
        $this->count['heading'] = empty($headings) ? 0 : 1;
        $this->count['column'] = count($headings);
        $this->rowHeadings = $headings;
        $this->excelHeadings = $titles;
        if (!empty($headings) && !isset($headings[0])) {
            $headings = array_values($headings);
        }
        $this->excelHeadings = array_merge($this->excelHeadings, [$headings]);
        return $this;
    }

    /**
     * 设置样式
     * 合并单元格,居中对齐等
     * 用法参见registerEvents
     *
     * @param Closure $style
     * @return $this
     */
    public function setStyle(\Closure $style)
    {
        $this->style = $style;
        return $this;
    }

    /**
     * 获取excel列名
     *
     * @param $index 0开始
     * @return string
     */
    public function getExcelTit($index)
    {
        $start = 65;
        $str = '';
        if (floor($index / 26) > 0) {
            $str .= $this->getExcelTit(floor($index / 26) - 1);
        }
        return $str . chr($index % 26 + $start);
    }
}

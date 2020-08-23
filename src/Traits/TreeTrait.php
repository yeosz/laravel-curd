<?php
/**
 * 树形结构通用方法
 *
 */

namespace Yeosz\LaravelCurd\Traits;

trait TreeTrait
{
    /**
     * 树形结构排序
     *
     * @param string $parentColumn 父级字段
     * @param string $sortColumn 排序的字段
     * @param string $query
     * @return array
     */
    protected function getTreeList($parentColumn, $sortColumn, $query)
    {
        if (is_string($query)) {
            $query = new $query;
        }

        $trees = $query->orderBy($parentColumn, 'asc')
            ->orderBy($sortColumn, 'asc')
            ->get();

        if ($start = strrpos($parentColumn, '.')) {
            $parentColumn = substr($parentColumn, $start + 1); // 处理表名如:a.parent_id
        }
        if ($start = strrpos($sortColumn, '.')) {
            $sortColumn = substr($sortColumn, $start + 1); // 处理表名如:a.parent_id
        }

        $tree = $this->treeSort($trees->toArray(), 'id', $parentColumn, $sortColumn, 'index_path');

        return $tree;
    }

    /**
     * 获取某节点的所有子节点ID
     *
     * @param string $query 模型
     * @param int $nodeId 节点ID
     * @param string $parentColumn 父级字段
     * @param bool $contain 是否包括当前元素
     * @return array
     */
    protected function getSubNodeIds($query, $nodeId, $parentColumn = 'parent_id', $contain = true)
    {
        if (is_string($query)) {
            $query = new $query;
        }

        /** @var \Illuminate\Database\Eloquent\Model $query */
        $trees = $query->orderBy($parentColumn, 'asc')->get();

        if ($start = strrpos($parentColumn, '.')) {
            $parentColumn = substr($parentColumn, $start + 1); // 处理表名如:a.parent_id
        }
        $tree = $this->treeSort($trees->toArray(), 'id', $parentColumn, 'id', 'index_path');

        $children = [$nodeId];
        foreach ($tree as $item) {
            if (in_array($item[$parentColumn], $children) && !in_array($item['id'], $children)) {
                $children[] = $item['id'];
            }
        }
        if (!$contain) {
            $children = array_slice($children, 1);
        }

        return $children;
    }

    /**
     * 树形数组排序
     *
     * @param array|\Illuminate\Support\Collection $arr 待排序数组
     * @param string $idField ID字段
     * @param string $pidField 父ID字段
     * @param string $sortField 排序字段
     * @param string $pathKey 表示路径的字段
     * @param string $treeDepth 表示深度的字段
     * @return array
     */
    protected function treeSort($arr, $idField, $pidField, $sortField, $pathKey = 'index_path', $treeDepth = 'tree_depth')
    {
        $getIndexPath = function ($row, $data) use (&$getIndexPath, $idField, $pidField, $pathKey) {
            if ($row[$pidField] == 0) return '0' . '.' . $row[$idField];
            foreach ($data as &$v) {
                if ($row[$pidField] == $v[$idField]) {
                    if (isset($v[$pathKey])) {
                        return $v[$pathKey] . '.' . $row[$idField];
                    } else {
                        return $getIndexPath($v, $data) . '.' . $row[$idField];
                    }
                }
            }
            throw new \Exception('parent node not exist:' . json_encode($row));
        };

        foreach ($arr as $id => $v) {
            $indexPath = $getIndexPath($v, $arr);
            $arr[$id][$pathKey] = $indexPath;
            $arr[$id][$treeDepth] = substr_count($indexPath, '.') - 1;
        }

        $arr = self::sortByColumn($arr, $sortField, 'asc');
        $tree = self::toTree($arr, $idField, $pidField);
        $result = self::treeToArr($tree);

        return $result;
    }

    /**
     * 生成树形结构
     *
     * @param array|\Illuminate\Support\Collection $list
     * @param string $pk
     * @param string $pid
     * @param string $child
     * @param int $root
     * @return array
     */
    public static function toTree($list, $pk = 'id', $pid = 'parent_id', $child = 'children', $root = 0)
    {
        // 创建Tree
        $tree = [];

        if (!(is_array($list) || ($list instanceof \Illuminate\Support\Collection))) {
            return $tree;
        }

        if ($list instanceof \Illuminate\Support\Collection) {
            $list = $list->toArray();
        }

        // 创建基于主键的数组引用
        $refer = [];
        foreach ($list as $key => $data) {
            $list[$key][$child] = [];
            $refer[$data[$pk]] =& $list[$key];
        }

        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId = $data[$pid];
            if ($root === $parentId || (empty($root) && empty($parentId))) {
                $tree[] =& $list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent =& $refer[$parentId];
                    $parent[$child][] =& $list[$key];
                }
            }
        }

        return $tree;
    }

    /**
     * 二维数组排序
     *
     * @param $arr
     * @param string $column
     * @param string $order
     * @return mixed
     */
    public static function sortByColumn($arr, $column = 'sort', $order = 'asc')
    {
        if (empty($arr)) return $arr;
        $mySort = function ($a, $b) use ($column, $order) {
            if ($order == 'asc') {
                return $a[$column] > $b[$column];
            } else {
                return $a[$column] < $b[$column];
            }
        };
        usort($arr, $mySort);
        return $arr;
    }

    /**
     * 树转array
     *
     * @param $tree
     * @param string $childColumn
     * @return array
     */
    public static function treeToArr($tree, $childColumn = 'children')
    {
        static $result = [];
        foreach ($tree as $node) {
            $children = empty($node[$childColumn]) ? [] : $node[$childColumn];
            if (isset($node[$childColumn])) unset($node[$childColumn]);
            $result[] = $node;
            if ($children) {
                self::treeToArr($children);
            }
        }
        return $result;
    }
}
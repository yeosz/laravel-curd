<?php

namespace Yeosz\LaravelCurd;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EBuilder;
use Illuminate\Http\Request;
use DB;

/**
 * Class Query
 * @package App\Services
 *
 * @method $this orWhere($column, $operator = null, $value = null)
 * @method $this where($column, $operator = null, $value = null, $boolean = 'and')
 * @method $this whereIn($column, $values, $boolean = 'and', $not = false)
 * @method $this whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method $this orWhereRaw($sql, $bindings = [])
 * @method $this limit($value)
 * @method $this when($value, $callback, $default = null)
 * @method $this orderBy($column, $direction)
 * @method $this select($columns = array())
 * @method \Illuminate\Pagination\LengthAwarePaginator  paginate($perPage = null, $columns = array(), $pageName = 'page', $page = null)
 * @method \Illuminate\Database\Eloquent\Collection get($columns = array())
 * @method \Illuminate\Support\Collection pluck($column, $key = null)
 * @method string toSql()
 */
class Query
{
    const METHODS = [
        [
            'where', 'orWhere', 'whereIn', 'orWhereIn', 'whereNotIn', 'orWhereNotIn',
            'when',
            'take', 'offset', 'skip', 'limit',
            'groupBy', 'having',
            'orderBy',
            'select',
            'whereRaw', 'orWhereRaw', 'orderByRaw',
        ],
        [
            'first', 'find', 'findOrFail',
            'get', 'paginate', 'count', 'pluck',
            'toSql',
        ]
    ];

    /**
     * @var Builder|EBuilder
     */
    private $query;

    /**
     * @var Builder|EBuilder
     */
    private $origin;

    /**
     * @var array
     */
    private $request;

    /**
     * @param Builder|EBuilder $query
     * @param Request|array|null $request
     */
    public function __construct($query, $request = null)
    {
        $this->origin = $query;
        $this->query = clone $query;
        $this->request = ($request instanceof Request) ? $request->all() : $request;
    }

    /**
     * 构建查询
     *
     * @param array $configs
     * @param bool $orderBy
     * @return $this
     */
    public function builder($configs, $orderBy = false)
    {
        foreach ($configs as $key => $config) {
            $config[2] = isset($config[2]) ? $config[2] : $config[0];
            if (is_array($config[1])) {
                call_user_func_array([$this, 'xWhenPluck'], $config);
            } else {
                call_user_func_array([$this, 'xWhen'], $config);
            }
        }
        if ($orderBy) {
            $this->xOrderBy();
        }
        return $this;
    }

    /**
     * 合计
     * 支持的函数有COUNT,MAX,AVG,MIN,MAX,SUM,GROUP_CONCAT
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator|Illuminate\Support\Collection $list
     * @param EBuilder|Builder $query 查询
     * @param array $columns 字段 $totalFields = ['count1' => 'count','table.f1','MIN(table.f2)']
     * @param string $foreignKey 外键
     * @param string $localKey 主键
     * @return mixed
     */
    public static function total($list, $query, $columns, $foreignKey, $localKey)
    {
        $getColumn = function ($column, $as) {
            if (is_numeric($as)) {
                $as = $column;
                $start = strpos($column, '.') + 1;
                if ($start > 1) {
                    $as = substr($column, $start);
                }
                $as = trim($as, ')');
            }
            $start = strpos($column, '(');
            if ($start > 0) {
                $func = strtoupper(substr($column, 0, $start));
                $field = DB::raw("{$column} AS {$as}");
            } else {
                $func = 'SUM';
                $field = DB::raw("SUM({$column}) AS {$as}");
            }
            $default = $func == 'GROUP_CONCAT' ? '' : '0';
            return [$field, $as, $default];
        };
        $fields = [$foreignKey];
        foreach ($columns as $key => $column) {
            $result = $getColumn($column, $key);
            $fields[] = $result[0];
            $columns[$key] = $result;
        }

        $keyBy = $getColumn($foreignKey, 0);
        $keyBy = $keyBy[1];
        $ids = $list->pluck($localKey)->toArray();
        $details = $query->whereIn($foreignKey, $ids)->groupBy($foreignKey)->get($fields)->keyBy($keyBy);
        foreach ($list as $item) {
            $total = $details[$item->{$localKey}] ?? null;
            foreach ($columns as $key => $column) {
                $field = $column[1];
                if (is_null($total)) {
                    $item->{$field} = $columns[$key][2];
                } else {
                    $item->{$field} = $total->{$field};
                }
            }
        }

        return $list;
    }

    /**
     *
     *
     * @param $key
     * @param string $operator %like,like,like%,=
     * @param string|array $column 数组表示可多个字段
     * @param bool $and and或or
     * @return $this
     */
    public function xWhen($key, $operator = '=', $column = '', $and = true)
    {
        $this->query->when(!empty($this->request[$key]), function ($query) use ($key, $column, $operator, $and) {
            $value = $this->request[$key];
            $column = empty($column) ? $key : $column;
            /** @var EBuilder $query */
            if (is_array($column)) {
                if ($and) {
                    $query->where(function ($query) use ($value, $column, $operator) {
                        /** @var EBuilder $query */
                        foreach ($column as $item) {
                            $query = $this->queryWhere($query, $item, $operator, $value, false);
                        }
                    });
                } else {
                    $query->orWhere(function ($query) use ($value, $column, $operator) {
                        /** @var EBuilder $query */
                        foreach ($column as $item) {
                            $query = $this->queryWhere($query, $item, $operator, $value, false);
                        }
                    });
                }
            } else {
                $query = $this->queryWhere($query, $column, $operator, $value, $and);
            }
            return $query;
        });

        return $this;
    }

    /**
     *
     *
     * @param string $key
     * @param array $table ['users','name','id','deleted_at is null']
     * @param string|array $column
     * @param bool $and
     * @return $this
     */
    public function xWhenPluck($key, $table, $column = '', $and = true)
    {
        if (!empty($this->request[$key])) {
            $value = is_array($this->request[$key]) ? $this->request[$key] : explode(',', $this->request[$key]);
            $column = empty($column) ? $key : $column;
            $query = DB::table($table[0])->whereIn($table[1], $value);
            if (isset($table[3])) {
                $query->whereRaw($table[3]);
            }
            $value = $query->pluck($table[2])->toArray();
            if (is_array($column)) {
                if ($and) {
                    $this->query->where(function ($query) use ($value, $column) {
                        /** @var EBuilder $query */
                        foreach ($column as $item) {
                            $query = $this->queryWhere($query, $item, 'in', $value, false);
                        }
                    });
                } else {
                    $this->query->orWhere(function ($query) use ($value, $column) {
                        /** @var EBuilder $query */
                        foreach ($column as $item) {
                            $query = $this->queryWhere($query, $item, 'in', $value, false);
                        }
                    });
                }
            } else {
                $this->queryWhere($this->query, $column, 'in', $value, $and);
            }
        }

        return $this;
    }

    /**
     * 排序
     *
     * @param string $orderBy
     * @param string $sort
     * @return $this
     */
    public function xOrderBy($orderBy = 'order_by', $sort = 'sort')
    {
        if (isset($this->request[$orderBy])) {
            $sort = (empty($this->request[$sort]) || $this->request[$sort] != 'desc') ? 'asc' : 'desc';
            $this->query->orderBy($this->request[$orderBy], $sort);
        }
        return $this;
    }

    /**
     *
     *
     * @param $relation
     * @param array $select
     * @param string $rawSql
     * @return $this
     */
    public function xWith($relation, $select = ['*'], $rawSql = '')
    {
        $relations = is_array($relation) ? $relation : [$relation, $select, $rawSql];
        foreach ($relations as $item) {
            $item[1] = $item[1] ?? ['*'];
            $item[2] = $item[2] ?? '';
            $this->query->with([$item[0] => function ($query) use ($item) {
                /** @var EBuilder $query */
                if ($item[2]) {
                    $query->whereRaw($item[2]);
                }
                $query->select($item[1]);
            }]);
        }
        return $this;
    }

    /**
     * 魔术方法
     *
     * @param $method
     * @param $args
     * @return $this|null
     */
    public function __call($method, $args)
    {
        if (in_array($method, self::METHODS[0])) {
            $this->query = call_user_func_array([$this->query, $method], $args);
            return $this;
        } elseif (in_array($method, self::METHODS[1])) {
            $data = call_user_func_array([$this->query, $method], $args);
            $this->query = clone $this->origin;
            return $data;
        }

        return null;
    }

    /**
     *
     *
     * @param $data
     * @return $this
     */
    public function setRequest($data)
    {
        $this->request = $data;
        return $this;
    }

    /**
     *
     *
     * @param EBuilder|Builder $query
     * @param string|array $column
     * @param string $operator
     * @param mixed $value
     * @param bool $and
     * @return EBuilder|Builder
     */
    private function queryWhere($query, $column, $operator, $value, $and)
    {
        /** @var $query Builder|EBuilder */
        switch ($operator) {
            case 'raw':
                $query = $and ? $query->whereRaw($column, $value) : $query->orWhereRaw($column, $value);
                break;
            case 'in':
                $value = is_array($value) ? $value : explode(',', $value);
                $query = $and ? $query->whereIn($column, $value) : $query->orWhereIn($column, $value);
                break;
            case 'not in':
                $value = is_array($value) ? $value : explode(',', $value);
                $query = $and ? $query->whereNotIn($column, $value) : $query->orWhereNotIn($column, $value);
                break;
            case '%like%':
            case 'like':
                $value = '%' . $value . '%';
                $query = $and ? $query->where($column, 'like', $value) : $query->orWhere($column, 'like', $value);
                break;
            case '%like':
                $value = '%' . $value;
                $query = $and ? $query->where($column, 'like', $value) : $query->orWhere($column, 'like', $value);
                break;
            case 'like%':
                $value = $value . '%';
                $query = $and ? $query->where($column, 'like', $value) : $query->orWhere($column, 'like', $value);
                break;
            default:
                $query = $and ? $query->where($column, $operator, $value) : $query->orWhere($column, $operator, $value);
        }
        return $query;
    }
}

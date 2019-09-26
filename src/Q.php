<?php

namespace Yeosz\LaravelCurd;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EBuilder;
use Illuminate\Http\Request;

/**
 * Class Q
 * @package App\Services
 *
 * @method Q orWhere($column, $operator = null, $value = null)
 * @method Q where($column, $operator = null, $value = null, $boolean = 'and')
 * @method Q whereIn($column, $values, $boolean = 'and', $not = false)
 * @method Q whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method Q orWhereRaw($sql, $bindings = [])
 * @method Q limit($value)
 * @method Q when($value, $callback, $default = null)
 * @method Q orderBy($column, $direction)
 * @method Q select($columns = array())
 * @method \Illuminate\Contracts\Pagination\LengthAwarePaginator  paginate($perPage = null, $columns = array(), $pageName = 'page', $page = null)
 * @method \Illuminate\Database\Eloquent\Collection get($columns = array())
 * @method \Illuminate\Support\Collection pluck($column, $key = null)
 * @method string toSql()
 */
class Q
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
                $query->where(function ($query) use ($value, $column, $operator, $and) {
                    /** @var EBuilder $query */
                    foreach ($column as $item) {
                        $query = $this->queryWhere($query, $item, $operator, $value, $and);
                    }
                });
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
                $this->query->where(function ($query) use ($value, $column, $and) {
                    /** @var EBuilder $query */
                    foreach ($column as $item) {
                        $query = $this->queryWhere($query, $item, 'in', $value, $and);
                    }
                });
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

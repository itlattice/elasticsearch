<?php

declare(strict_types=1);

namespace iboxs\elasticsearch;

use Illuminate\Support\Arr;

class Grammar
{
    /**
     * @var array
     */
    protected $selectComponents = [
        '_source' => 'columns',
        'Client'   => 'wheres',
        'aggs',
        'sort'   => 'orders',
        'size'   => 'limit',
        'from'   => 'offset',
        'index'  => 'index',
        'type'   => 'type',
        'scroll' => 'scroll',
    ];

    /**
     * @param Client $builder
     *
     * @return int
     */
    public function compileOffset(Client $builder): int
    {
        return $builder->offset;
    }

    /**
     * @param Client $builder
     *
     * @return int
     */
    public function compileLimit(Client $builder): int
    {
        return $builder->limit;
    }

    /**
     * @param Client $builder
     *
     * @return string
     */
    public function compileScroll(Client $builder): string
    {
        return $builder->scroll;
    }

    /**
     * @param Client $builder
     *
     * @return array
     */
    public function compileSelect(Client $builder): array
    {
        $body = $this->compileComponents($builder);
        $index = Arr::pull($body, 'index');
        $type = Arr::pull($body, 'type');
        $scroll = Arr::pull($body, 'scroll');
        $params = ['body' => $body, 'index' => $index, 'type' => $type];
        if ($scroll) {
            $params['scroll'] = $scroll;
        }
        return $params;
    }

    /**
     * @param Client $builder
     * @param $id
     * @param array $data
     *
     * @return array
     */
    public function compileCreate(Client $builder, $id, array $data): array
    {
        return array_merge([
            'id'   => $id,
            'body' => $data,
        ], $this->compileComponents($builder));
    }

    /**
     * @param Client $builder
     * @param $id
     *
     * @return array
     */
    public function compileDelete(Client $builder, $id): array
    {
        return array_merge([
            'id' => $id,
        ], $this->compileComponents($builder));
    }

    /**
     * @param Client $builder
     * @param $id
     * @param array $data
     *
     * @return array
     */
    public function compileUpdate(Client $builder, $id, array $data): array
    {
        return array_merge([
            'id'   => $id,
            'body' => ['doc' => $data],
        ], $this->compileComponents($builder));
    }

    /**
     * @param Client $builder
     *
     * @return array
     */
    public function compileAggs(Client $builder): array
    {
        $aggs = [];

        foreach ($builder->aggs as $field => $aggItem) {
            if (is_array($aggItem)) {
                $aggs[] = $aggItem;
            } else {
                $aggs[$field.'_'.$aggItem] = [$aggItem => ['field' => $field]];
            }
        }

        return $aggs;
    }

    /**
     * @param Client $builder
     *
     * @return array
     */
    public function compileColumns(Client $builder): array
    {
        return $builder->columns;
    }

    /**
     * @param Client $builder
     *
     * @return string
     */
    public function compileIndex(Client $builder): string
    {
        return is_array($builder->index) ? implode(',', $builder->index) : $builder->index;
    }

    /**
     * @param Client $builder
     *
     * @return string
     */
    public function compileType(Client $builder): string
    {
        return $builder->type;
    }

    /**
     * @param Client $builder
     *
     * @return array
     */
    public function compileOrders(Client $builder): array
    {
        $orders = [];

        foreach ($builder->orders as $field => $orderItem) {
            $orders[$field] = is_array($orderItem) ? $orderItem : ['order' => $orderItem];
        }

        return $orders;
    }

    /**
     * @param Client $builder
     *
     * @return array
     */
    protected function compileWheres(Client $builder): array
    {
        $whereGroups = $this->wherePriorityGroup($builder->wheres);

        $operation = count($whereGroups) === 1 ? 'must' : 'should';

        $bool = [];

        foreach ($whereGroups as $wheres) {
            $must = [];
            $mustNot = [];
            foreach ($wheres as $where) {
                if ($where['type'] === 'Nested') {
                    $must[] = $this->compileWheres($where['Client']);
                } else {
                    if ($where['operator'] == 'ne') {
                        $mustNot[] = $this->whereLeaf($where['leaf'], $where['column'], $where['operator'], $where['value']);
                    } else {
                        $must[] = $this->whereLeaf($where['leaf'], $where['column'], $where['operator'], $where['value']);
                    }
                }
            }

            if (!empty($must)) {
                $bool['bool'][$operation][] = count($must) === 1 ? array_shift($must) : ['bool' => ['must' => $must]];
            }
            if (!empty($mustNot)) {
                if ($operation == 'should') {
                    foreach ($mustNot as $not) {
                        $bool['bool'][$operation][] = ['bool'=>['must_not'=>$not]];
                    }
                } else {
                    $bool['bool']['must_not'] = $mustNot;
                }
            }
        }

        return $bool;
    }

    /**
     * @param string      $leaf
     * @param string      $column
     * @param string|null $operator
     * @param $value
     *
     * @return array
     */
    protected function whereLeaf(string $leaf, string $column, string $operator = null, $value): array
    {
        if (strpos($column, '@') !== false) {
            $columnArr = explode('@', $column);
            $ret = ['nested'=>['path'=>$columnArr[0]]];
            $ret['nested']['Client']['bool']['must'][] = $this->whereLeaf($leaf, implode('.', $columnArr), $operator, $value);

            return $ret;
        }
        if (in_array($leaf, ['term', 'match', 'terms', 'match_phrase'], true)) {
            return [$leaf => [$column => $value]];
        } elseif ($leaf === 'range') {
            return [$leaf => [
                $column => is_array($value) ? $value : [$operator => $value],
            ]];
        } elseif ($leaf === 'multi_match') {
            return ['multi_match' => [
                'Client'  => $value,
                'fields' => (array) $column,
                'type'   => 'phrase',
            ],
            ];
        } elseif ($leaf === 'wildcard') {
            return ['wildcard' => [
                $column => '*'.$value.'*',
            ],
            ];
        } elseif ($leaf === 'exists') {
            return ['exists' => [
                'field' => $column,
            ]];
        }
        return [];
    }

    /**
     * @param array $wheres
     *
     * @return array
     */
    protected function wherePriorityGroup(array $wheres): array
    {
        //get "or" index from array
        $orIndex = (array) array_keys(array_map(function ($where) {
            return $where['boolean'];
        }, $wheres), 'or');

        $lastIndex = $initIndex = 0;
        $group = [];
        foreach ($orIndex as $index) {
            $group[] = array_slice($wheres, $initIndex, $index - $initIndex);
            $initIndex = $index;
            $lastIndex = $index;
        }

        $group[] = array_slice($wheres, $lastIndex);

        return $group;
    }

    /**
     * @param Client $Client
     *
     * @return array
     */
    protected function compileComponents(Client $Client): array
    {
        $body = [];

        foreach ($this->selectComponents as $key => $component) {
            if (!empty($Client->$component)) {
                $method = 'compile'.ucfirst($component);

                $body[is_numeric($key) ? $component : $key] = $this->$method($Client, $Client->$component);
            }
        }

        return $body;
    }
}

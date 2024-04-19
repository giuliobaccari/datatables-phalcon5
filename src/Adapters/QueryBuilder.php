<?php
namespace DataTables\Adapters;
use Phalcon\Paginator\Adapter\QueryBuilder as PQueryBuilder;

class QueryBuilder extends AdapterInterface{
  protected $builder;
  private $global_search;
  private $column_search;
  private $debug;
  private $_bind;

  public function setBuilder($builder) {
    $this->builder = $builder;
  }

  public function getResponse() {
    $builder = new PQueryBuilder([
      'builder' => $this->builder,
      'limit'   => 1,
      'page'    => 1,
    ]);

    $total = $builder->paginate();
    $this->global_search = [];
    $this->column_search = [];

    $this->bind('global_search', false, function($column, $search) {
      $key = "keyg_" . str_replace(".", "", $column);
      $this->global_search[] = "{$column} LIKE :{$key}:";
      $this->_bind[$key] = "%{$search}%";
    });

    $this->bind('column_search', false, function($column, $search) {
      if(str_contains($search, '|'))
      {
        $key = "keyc_" . str_replace(" ", "", str_replace(".", "", $column));
        $this->debug['search'][] = $search;

        $lista = explode("|",$search);
        $this->debug['lista'][] = $lista;

        $filtro = array();
        $contatore = 0;
        $catena = "( ";
        $i=1;
        foreach ($lista as $v)
        {
          $contatore++;
          // $v = str_replace("^", "", str_replace("$", "", $v));
          $v = str_replace(PHP_EOL, '', $v);
          $filtro[$contatore] = $v;

          $this->debug['elementi'][] = $filtro[$contatore];

        }
        //print_r($filtro);
        for ($i=1;$i<$contatore;$i++)
        {
          $catena .= "{$column} LIKE :{$i}: OR ";
          $this->_bind[$i] = "%{$filtro[$i]}%";

          $this->debug[] = "%{$filtro[$i]}%";

        }
        if($i==$contatore)
        {
          $catena .= "{$column} LIKE :{$i}: ";
          $catena .= " ) ";
          $this->column_search[] = $catena;
          $this->_bind[$i] = "%{$filtro[$i]}%";

          $this->debug[] = "%{$filtro[$i]}%";

        }
        $this->debug[] = $catena;


      }
      else
      {
        $key = "keyc_" . str_replace(" ", "", str_replace(".", "", $column));
        $this->column_search[] = "{$column} LIKE :{$key}:";
        $this->_bind[$key] = "%{$search}%";
      }
    });

    $this->bind('order', false, function($order) {
      if (!empty($order)) {
        $this->builder->orderBy(implode(', ', $order));
      }
    });

    if (!empty($this->global_search) || !empty($this->column_search)) {
      $where = implode(' OR ', $this->global_search);
      if (!empty($this->column_search))
        $where = (empty($where) ? '' : ('(' . $where . ') AND ')) . implode(' AND ', $this->column_search);
      $this->builder->andWhere($where, $this->_bind);
    }

    $builder = new PQueryBuilder([
      'builder' => $this->builder,
      'limit'   => $this->parser->getLimit($total->total_items),
      'page'    => $this->parser->getPage(),
    ]);

    $filtered = $builder->paginate();
    // $sql = $builder->toSql();
    // $this->debug[] = $sql;

    return $this->formResponse([
      'total'     => $total->total_items,
      'filtered'  => $filtered->total_items,
      'data'      => $filtered->items->toArray(),
      'debug'     => $this->debug

    ]);
  }
}

<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
namespace startmvc\core;
use PDO;
use PDOException;
use Closure;
use startmvc\core\Cache;

if(!defined('_AND')) define('_AND', 'AND');
if(!defined('_OR'))  define('_OR',  'OR');

class Db
{
    private $pdo;
    private $config;

    private $fetchMode     = PDO::FETCH_ASSOC;//PDO::FETCH_OBJ
    private $toJson        = false;

    private $queryHistory  = [];
    private $rowCount      = 0;

    private $rawQuery      = null;

    private $select         = null;
    private $table          = null;
    private $join           = null;
    private $where          = null;
    private $order          = null;
    private $group          = null;
    private $having         = null;
    private $limit          = null;
    private $offset         = null;

    private $pager;
    private $pagerRows;
    private $pagerData     = [];
    private $pagerTemplate = '<li class="{active}"><a href="{url}">{text}</a></li>';
    private $pagerHtml;

    private $isGrouped     = false;
    private $isGroupIn     = false;

    private $isFilter      = false;
    private $isFilterValid = false;

    private $joinNodes     = [];
    private $joinParams    = [];
    private $havingParams  = [];
    private $whereParams   = [];
    private $rawParams     = [];
    
    private $cache=null;
    private $cacheType=null;
    
    private $prefix;

    /**
     * __construct
     *
     * @param array $config
     */
    public function __construct($config = null)
    {
        $defaultConfig = [
            'host'      => 'localhost',
            'driver'    => 'mysql',
            'database'  => '',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ];
		$this->config = array_merge($defaultConfig, $config);
		$this->prefix=$this->config['prefix'];
		
        $dsnList = [
            'mysql'  => "dbname={$this->config['database']};host={$this->config['host']}",
            'sqlite' => "{$this->config['database']}"
        ];

        if(!array_key_exists($this->config['driver'], $dsnList)){
            throw new \Exception('driver bulunamadı...');
            return;
        }
        
        $options = [
            PDO::ATTR_PERSISTENT         => false, //
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => $this->fetchMode,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']} COLLATE {$this->config['collation']}"
        ];

        try {
            $this->pdo = new PDO("{$this->config['driver']}:{$dsnList[$this->config['driver']]}", $this->config['username'], $this->config['password'], $options);
            if($this->config['driver'] == 'sqlite')
                $this->pdo->exec('PRAGMA foreign_keys=ON');
        } catch (PDOException $e) { 
            throw new \Exception($e->getMessage()); 
        }
    }

    /**
     * init
     */
    protected function init(){
        
        $this->fetchMode     = PDO::FETCH_ASSOC;//PDO::FETCH_OBJ
        $this->toJson        = false;
        $this->rowCount      = 0;
        $this->cache         = null;
        $this->redisActive   = false;
        $this->cacheType   = null;
        $this->rawQuery      = null;
        $this->select        = null;
        $this->table         = null;
        $this->join          = null;
        $this->where         = null;
        $this->order         = null;
        $this->group         = null;
        $this->having        = null;
        $this->limit         = null;
        $this->offset        = null;
        $this->pager         = null;
        $this->isGrouped     = false;
        $this->isGroupIn     = false;
        $this->isFilter      = false;
        $this->isFilterValid = false;
        $this->joinNodes     = [];
        $this->joinParams    = [];
        $this->havingParams  = [];
        $this->whereParams   = [];
        $this->rawParams     = [];
    }
    
    /**
     * Closure and-or grouping
     *
     * @param closure $object
     * @return $this
     */
    public function grouped(Closure $object){
        $this->isGrouped = true;
        call_user_func_array($object, [$this]);
        $this->where .= ')';
        return $this;
    } 

    /**
     * The AND-OR grouping in the query
     *
     * @param bool $andOr
     */
    protected function setGroup($andOr = false){
        $this->isGroupIn = $andOr;
    }
    
    /**
     * select
     *
     * @param string|array $fields
     * @return $this
     */
    public function select($fields){
        $select = is_array($fields) 
            ? implode(', ', $fields) 
            : $fields;
        $this->select = !is_null($this->select) 
            ? $this->select . ', '. $select 
            : $select;
        return $this;
    }                
    
    /**
     * selectBuild
     *
     * @return string
     */
    protected function selectBuild(){
        return $this->select ? $this->select : '*';
    }
    
    /**
     * selectInit
     */
    protected function selectFlush($select = null){
        $this->select = $select;
    }
    
    /**
     * selectFunctions
     *
     * @param string $field
     * @param string $alias
     * @param string $function
     */
    protected function selectFunctions($field, $alias = null, $function = null){
        return $this->select($alias ? $function.'('.$field.') AS '.$alias : $function.'('.$field.')');
    }        

    /**
     * count
     *
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function count($field = '*', $alias = null){
        return $this->selectFunctions($field, $alias, 'COUNT');
    }       
        
    /**
     * sum
     *
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function sum($field, $alias = null){
        return $this->selectFunctions($field, $alias, 'SUM');
    }        
    
    /**
     * avg
     *
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function avg($field, $alias = null){
        return $this->selectFunctions($field, $alias, 'AVG');
    }        
    
    /**
     * min
     *
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function min($field, $alias = null){
        return $this->selectFunctions($field, $alias, 'MIN');
    }      

    /**
     * max
     *
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function max($field, $alias = null){
        return $this->selectFunctions($field, $alias, 'MAX');
    }


    /**
     * table
     *
     * @param string|array $table
     * @return $this
     */
    public function table($table, $as = null)
    {
        $prefix = $this->config['prefix'];

        if (is_array($table)) {
            // 对数组中的每个表名添加前缀
            $table = array_map(function ($tableName) use ($prefix) {
                return $prefix . $tableName;
            }, $table);

            $table = implode(', ', $table);
        } else {
            if (!empty($as)) {
                $table = $table . ' AS ' . $as;
            }

            // 自动加上表前缀
            $table = $prefix . $table;
        }

        $this->table = $table;
        return $this;
    }
    /**
     * table
     *
     * @param string|array $table
     * @return $this
     */
    //public function table($table, $as = null){
    //    $this->table = is_array($table) 
    //        ? implode(', ', $table) 
    //        : (!empty($as) ? ($table . ' AS ' . $as) : $table);
    //    return $this;
    //}
    
    /**
     * from alias table
     *
     * @param string|array $table
     * @return $this
     */
    public function from($table, $as = null){
        $this->table($table, $as);
        return $this;
    }
    
    /**
     * tableBuild
     *
     * @return string
     */
    protected function tableBuild(){
        if(!$this->table)
            throw new Exception('Tablo seçilmeden devam edilemez.');
        return $this->table;
    }
    
    /**
     * join
     *
     * @param string $from
     * @param string $field
     * @param string $params
     * @param string $join
     * @return $this
     */
    public function join($from, $field = null, $params = null, $join = 'INNER'){
	    $from=$this->prefix.$from;
        if(!is_null($field)){
            if(!is_null($params))
                $field = $field . '=' . $params;
            $join = $join . ' JOIN ' . $from . ' ON ' . $field;
        } else {
            $join = $join . ' JOIN ' . $from;
        }
        $this->join = !is_null($this->join) ? $this->join . ' '. $join : $join;
        return $this;
    }

    /**
     * leftJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function leftJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'LEFT');
    }

    /**
     * leftOuterJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function leftOuterJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'LEFT OUTER');
    }

    /**
     * rightJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function rightJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'RIGHT');
    }

    /**
     * rightOuterJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function rightOuterJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'RIGHT OUTER');
    }

    /**
     * innerJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function innerJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'INNER');
    }

    /**
     * fullOuterJoin
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function fullOuterJoin($from, $on = null, $params = null){
        return $this->join($from, $on, $params, 'FULL OUTER');
    }

    /**
     * joinBuild
     *
     * @param string $from
     * @param string $on
     * @param string $params
     * @return $this
     */
    public function joinBuild(){
        return $this->join ? $this->join : null;
    }

    /**
     * joinNode
     *
     * @param string $alias
     * @param array $columns
     * @return $this
     */
    public function joinNode($alias, $columns){

        if(is_null($this->joinBuild()))
            return $this;
        
        $this->joinNodes[] = $alias;

        $cols = array_map(function($k, $v){
            return "'{$k}', {$v}";
        }, array_keys($columns), array_values($columns));

        $this->select("IF(ISNULL(".current($columns)."), JSON_ARRAY(), CONCAT('[', GROUP_CONCAT(JSON_OBJECT(" . implode(', ', $cols) . ")), ']')) AS {$alias}");
        return $this;
    }

    /**
     * nodeParser
     *
     * @param mixed $data
     * @return array|object
     */
    public function nodeParser($results){
        array_walk_recursive($results, function(&$v, $k){
            if(is_object($v)){
                return $this->nodeParser($v);
            }
            if(in_array($k, $this->joinNodes)){
                $v = json_decode($v, $this->fetchMode == PDO::FETCH_ASSOC ? true : false);
            }
        });
        return $results;
    }
    
    /**
     * order
     *
     * @param string|array $order
     * @param string $dir
     * @return $this
     */
    public function order($order, $dir = null){
        if(!is_null($dir)){
            $this->order = $order . ' ' . $dir;
        } else{
            $this->order = stristr($order, ' ') || $order == 'rand()'
                ? $order
                : $order . ' DESC';
        }
        return $this;
    }

    /**
     * orderBuild
     *
     * @return string
     */
    public function orderBuild(){
        return $this->order ? 'ORDER BY ' . $this->order : null;
    }
    
    /**
     * group
     *
     * @param string|array $group
     * @return $this
     */
    public function group($group){
        $this->group = is_array($group) ? implode(', ', $group) : $group;
        return $this;
    }
    public function groupBuild(){
        return $this->group ? 'GROUP BY ' . $this->group : null;
    }
    
    /**
     * limit
     *
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = null){
        $this->limit  = $limit;
        $this->offset = $offset;
        return $this;
    }
            
    /**
     * offset
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset){
        $this->offset = $offset;
        return $this;
    }
                    
    /**
     * pager
     *
     * @param mixed $limit
     * @param mixed $page
     * @return $this
     */
    public function pager(int $limit, int $page = 1){
        
        if($limit < 1) $limit = 1;
        if($page  < 1) $page  = 1;

        $this->limit  = $limit;
        $this->offset = ($limit * $page) - $limit;
        $this->pager  = $page;
        
        return $this;
    }

    /**
     * pagerRows
     *
     * @param  mixed $total
     * @return $this
     */
    public function pagerRows(int $total){
        $this->pagerRows = $total;
        return $this;
    }
            
    /**
     * pagerLinks
     *
     * @param mixed $url
     * @param mixed $class
     * @return void
     */
    public function pagerLinks($url = '?page={page}', $class = 'active'){
        if(isset($this->pagerData['total'])){
            $totalPage = $this->pagerData['total'];
            if($totalPage <= 10){
                $min = 1;
                $max = $totalPage;
            } else {
                $min = max(1, ($this->pagerData['current'] - 5));
                $max = min($totalPage, ($this->pagerData['current'] + 5));
                if($min === 1){
                    $max = 10;
                } elseif($max === $totalPage) {
                    $min = ($totalPage - 9);
                }
            }
            // first
            $this->pagerHtml .= str_replace(
                ['{active}', '{text}', '{url}'],
                [null, '«', str_replace('{page}', 1, $url)],
                $this->pagerTemplate
            );
            // prev
            $this->pagerHtml .= str_replace(
                ['{active}', '{text}', '{url}'],
                [null, '‹', str_replace('{page}', ($this->pagerData['current'] > 1 ? $this->pagerData['current'] - 1 : 1), $url)],
                $this->pagerTemplate
            );
            // pages
            for($i = $min; $i <= $max; $i++){
                $this->pagerHtml .= str_replace(
                    ['{active}', '{text}', '{url}'],
                    [($i == $this->pagerData['current'] ? $class : null), $i, str_replace('{page}', $i, $url)],
                    $this->pagerTemplate
                );
            }
            // next
            $this->pagerHtml .= str_replace(
                ['{active}', '{text}', '{url}'],
                [null, '›', str_replace('{page}', ($this->pagerData['current'] < $totalPage ? $this->pagerData['current'] + 1 : $totalPage), $url)],
                $this->pagerTemplate
            );
            // last
            $this->pagerHtml .= str_replace(
                ['{active}', '{text}', '{url}'],
                [null, '»', str_replace('{page}', $totalPage, $url)],
                $this->pagerTemplate
            );
            return $this->pagerHtml;
        }
    }
    
    /**
     * pagerData
     *
     * @return void
     */
    public function pagerData(){
        return $this->pagerData;
    }
    
    /**
     * pagerData
     *
     * @param mixed $template
     * @return void
     */
    public function setPagerTemplate($template){
        $this->pagerTemplate = $template;
    }
            
    /**
     * limitOffsetBuild
     *
     * @return string
     */
    public function limitOffsetBuild(){
        return ($this->limit ? 'LIMIT ' . (int)$this->limit : null).($this->offset ? ' OFFSET ' . (int)$this->offset : null);
    }
    
    /**
     * having
     *
     * @param string|array $field
     * @param string $value
     * @return $this
     */
    public function having($field, $value = null){
        if($this->findMarker($field)){
            $this->having = $field;
        } else {
            $this->having = !is_null($value) ? $field . ' > ' .$value : $field;
        }
        $this->addHavingParams($value);
        return $this;
    }        
    
    /**
     * havingBuild
     *
     * @return void
     */
    public function havingBuild(){
        return $this->having ? 'HAVING ' . $this->having : null;
    }

    /**
     * where
     *
     * @param string|array $column
     * @param string|array $value
     * @param string $andOr
     * @return $this
     */
    public function where($column, $value = null, $andOr = _AND){
        return $this->whereFactory($column, $value, $andOr);
    }
            
    /**
     * orWhere
     *
     * @param string|array $column
     * @param string|array $value
     * @return $this
     */
    public function orWhere($column, $value = null){
        return $this->where($column, $value, _OR);
    }
            
    /**
     * notWhere
     *
     * @param string|array $column
     * @param string|array $value
     * @param string $andOr
     * @return $this
     */
    public function notWhere($column, $value = null, $andOr = _AND){
        return $this->whereFactory($column, $value, $andOr, "%s <> ?");
    }
            
    /**
     * orNotWhere
     *
     * @param string|array $column
     * @param string|array $value
     * @return $this
     */
    public function orNotWhere($column, $value = null){
        return $this->notWhere($column, $value, _OR);
    }
            
    /**
     * whereBuild
     *
     * @return string
     */
    protected function whereBuild(){
        return !is_null($this->where) ? 'WHERE ' . $this->where : null;
    }

    /**
     * whereBuildRaw
     *
     * @return string
     */
    protected function whereBuildRaw(){
        return !is_null($this->where) ? vsprintf(str_replace('?', '%s', $this->whereBuild()), $this->whereParams) : null;
    }
    
    /**
     * isNull
     *
     * @param string|array $column
     * @param string $group
     * @param string $andOr
     * @return $this
     */
    public function isNull($column, $group = null, $andOr = _AND){
        return $this->whereFactory($column, $group, $andOr, "%s IS NULL", true);
    }

    /**
     * orIsNull
     *
     * @param string|array $column
     * @param string $group
     * @param string $andOr
     * @return $this
     */
    public function orIsNull($column, $group = null){
        return $this->isNull($column, $group, _OR);
    }

    /**
     * notNull
     *
     * @param string|array $column
     * @param string $group
     * @param string $andOr
     * @return $this
     */
    public function notNull($column, $group = null, $andOr = _AND){
        return $this->whereFactory($column, $group, $andOr, "%s IS NOT NULL", true);
    }

    /**
     * orNotNull
     *
     * @param string|array $column
     * @param string $group
     * @param string $andOr
     * @return $this
     */
    public function orNotNull($column, $group = null){
        return $this->notNull($column, $group, _OR);
    }
    
    /**
     * in
     *
     * @param string $column
     * @param array  $value
     * @param string $andOr
     * @return $this
     */
    public function in($column, $value, $andOr = _AND){
        return $this->whereFactory($column, (array)$value, $andOr, "%s IN({$this->createMarker((array)$value)})");
    }

    /**
     * orIn
     *
     * @param string $column
     * @param array  $value
     * @return $this
     */
    public function orIn($column, $value){
        return $this->in($column, $value, _OR);
    }

    /**
     * notIn
     *
     * @param string $column
     * @param array  $value
     * @return $this
     */
    public function notIn($column, $value, $andOr = _AND){
        return $this->whereFactory($column, (array)$value, $andOr, "%s NOT IN({$this->createMarker((array)$value)})");
    }

    /**
     * orNotIn
     *
     * @param string $column
     * @param array  $value
     * @return $this
     */
    public function orNotIn($column, $value){
        return $this->in($column, $value, _OR);
    }
    
    /**
     * between
     *
     * @param string $column
     * @param int    $begin
     * @param int    $end
     * @param string $andOr
     * @return $this
     */
    public function between($column, int $begin, int $end, $andOr = _AND){
        return $this->whereFactory($column, [$begin, $end], $andOr, "%s BETWEEN ? AND ?");
    }

    /**
     * orBetween
     *
     * @param string $column
     * @param int    $begin
     * @param int    $end
     * @return $this
     */
    public function orBetween($column, int $begin, int $end){
        return $this->between($column, $begin, $end, _OR);
    }

    /**
     * notBetween
     *
     * @param string $column
     * @param int    $begin
     * @param int    $end
     * @param string $andOr
     * @return $this
     */
    public function notBetween($column, int $begin, int $end, $andOr = _AND){
        return $this->whereFactory($column, [$begin, $end], $andOr, "%s NOT BETWEEN ? AND ?");
    }

    /**
     * orNotBetween
     *
     * @param string $column
     * @param int    $begin
     * @param int    $end
     * @return $this
     */
    public function orNotBetween($column, int $begin, int $end){
        return $this->between($column, $begin, $end, _OR);
    }
    
    /**
     * findInSet
     *
     * @param string $column
     * @param string $search
     * @param string $andOr
     * @return $this
     */
    public function findInSet($column, $search, $andOr = _AND){
        return $this->whereFactory(null, $search, $andOr, "FIND_IN_SET(?, {$column})");
    }

    /**
     * orFindInSet
     *
     * @param string $column
     * @param string $search
     * @return $this
     */
    public function orFindInSet($column, $search){
        return $this->findInSet($column, $search, _OR);
    }

    /**
     * notFindInSet
     *
     * @param string $column
     * @param string $search
     * @param string $andOr
     * @return $this
     */
    public function notFindInSet($column, $search, $andOr = _AND){
        return $this->whereFactory(null, $search, $andOr, "NOT FIND_IN_SET(?, {$column})");
    }

    /**
     * orNotFindInSet
     *
     * @param string $column
     * @param string $search
     * @return $this
     */
    public function orNotFindInSet($column, $search){
        return $this->notFindInSet($column, $search, _OR);
    }

    /**
     * like
     *
     * @param string $column
     * @param string|array $search
     * @param string $group
     * @param string $andOr
     * @param string $pattern
     * @return $this
     */
    public function like($column, $search, $group = null, $andOr = _AND, $pattern = '%s LIKE ?'){
        $params = [];
        $tmpcol = (array)$column;
        foreach($tmpcol as $val) $params[sprintf($pattern, $val)] = $search;
        return $this->whereFactory($params, is_array($column) ? _OR : $group, $andOr);
    }
            
    /**
     * orLike
     *
     * @param string $column
     * @param string|array $search
     * @param string $group
     * @return $this
     */
    public function orLike($column, $search, $group = null){
        return $this->like($column, $search, $group, _OR);
    }

    /**
     * notLike
     *
     * @param string $column
     * @param string|array $search
     * @param string $group
     * @return $this
     */
    public function notLike($column, $search, $group = null){
        return $this->like($column, $search, $group, _AND, '%s NOT LIKE ?');
    }

    /**
     * orNotlike
     *
     * @param string $column
     * @param string|array $search
     * @param string $group
     * @return $this
     */
    public function orNotlike($column, $search, $group = null){
        return $this->like($column, $search, $group, _OR, '%s NOT LIKE ?');
    }
    
    /**
     * Search for a marker within a string
     *
     * @param string $string
     * @return bool
     */
    public function findMarker($string){
        return strpos($string, '?') !== FALSE;
    }
    
   /**
     * Creates as many markers as parameters for the query
     *
     * @param mixed $params
     * @return string
     */
    public function createMarker($params){
        if(!is_array(reset($params))):
            return rtrim(str_repeat('?,', sizeof($params)), ',');
        else:
            array_walk($params, function(&$val, $key){
                $val = $this->createMarker($val);
            });
            return '('.implode('),(', $params).')';
        endif;
    }
            
    /**
     * Creates pattern for query
     *
     * @param array  $params
     * @param string $pattern
     * @param mixed  $comma
     * @return string
     */
    public function createMarkerWithKey($params, $pattern = '%key=?', $comma = ','){
        $params = is_array(reset($params)) ? $params[0] : $params;
        if(is_array($params)){
            array_walk($params, function(&$val, $key) use ($pattern){
                $val = str_replace(['%val', '%key'], [$val, $key], $pattern);
            });
            return implode($comma, $params);
        } else{
            return str_replace(['%val', '%key'], [$params, $params], $pattern);
        }
    }

    /**
     * addParams
     *
     * @param array $params
     * @param string $type
     * @return void
     */
    protected function addParams($params, $type = 'whereParams'){
        if(is_array($params))
            foreach($params as $p) $this->$type[] = $p;
        else
            if(!is_null($params))
                $this->$type[] = $params;
    }
            
    /**
     * delParams
     *
     * @param string $key
     * @return void
     */
    protected function delParams($key){
        if(isset($this->$key))
            $this->$key = [];
    }
            
    /**
     * addWhereParams
     *
     * @param array $params
     * @return void
     */
    public function addWhereParams($params){
        $this->addParams($params);
    }
            
    /**
     * addJoinParams
     *
     * @param array $params
     * @return void
     */
    public function addJoinParams($params){
        $this->addParams($params, 'joinParams');
    }    

    /**
     * addHavingParams
     *
     * @param array $params
     * @return void
     */
    public function addHavingParams($params){
        $this->delParams('havingParams');
        $this->addParams($params, 'havingParams');
    }        

    /**
     * addRawParams
     *
     * @param array $params
     * @return void
     */
    public function addRawParams($params){
        $this->addParams($params, 'rawParams');
    }
    
    /**
     * raw
     *
     * @param string $query
     * @param string|array $params
     * @return $this
     */
    public function query($query, $params = null){
        if(!is_null($params))
            $this->addRawParams($params);
        $this->rawQuery = $query;
        return $this;
    }

    /**
     * exec
     *
     * @return int
     */
    public function exec(){
        $runQuery = $this->pdo->prepare($this->rawQuery);
        $runQuery->execute($this->rawParams);
        $this->killQuery($this->rawQuery, $this->rawParams);
        return $runQuery->rowCount();
    }
    
    /**
     * whereFactory
     *
     * @param string|array $column
     * @param string|array $value
     * @param string $andOr
     * @param string $pattern
     * @param bool $withoutParam
     * @return $this
     */
    public function whereFactory($column, $value = null, $andOr = _AND, $pattern = "%s=?", $withoutParam = false){

        $where = [];
        $param = [];

        if(is_array($column)){

            foreach($column as $key => $val){

                // key => val
                if(!is_numeric($key)){

                    // Is there a marker in the key?
                    if($this->findMarker($key)){
                        $where[] = $key;
                        $param[] = $val;

                    } else{
                        $param[] = $val; // key => val
                        $where[] = sprintf($pattern, $key);
                    }

                } else{

                    // If no parameter is being sent (see isNull)
                    $where[] = $withoutParam 
                        ? sprintf($pattern, $val) 
                        : $val;
                }
            }

            // If Value sent group information
            if(!is_null($value))
                if($value === _AND || $value === _OR) 
                    $this->setGroup($value);
                else
                    $this->addWhereParams($value);
            
            if($param)
                $this->addWhereParams($param);
        
        } else{

            if(!is_null($value)){
                
                $where[] = !$this->findMarker($column) 
                    ? sprintf($pattern, $column) 
                    : $column;

                $this->addWhereParams($value);
            
            } else{

                // If no parameter is sent (see isNull)
                $where[] = $withoutParam 
                        ? sprintf($pattern, $column) 
                        : $column;
            }
        }
        
        // Group if in-query group is requested
        if($this->isGroupIn)
            $where = '(' . implode(' ' . $this->isGroupIn . ' ', $where) . ')'; 
        else
            $where = implode(' ' . $andOr . ' ', $where);
        $this->setGroup();

        if($this->isGrouped)
            $where = '(' . $where; $this->isGrouped = false;
        
        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }
    
    /**
     * getReadParams
     *
     * @return array
     */
    public function getReadParams(){
        if($this->rawQuery)
            return $this->rawParams;
        else
            return array_merge($this->joinParams, $this->whereParams, $this->havingParams);
    }
    
    /**
     * getReadQuery
     *
     * @return string
     */
    public function getReadQuery($deny = []){
        
        if($this->rawQuery) return $this->rawQuery;

        $build = [
            'selectPrefix' => 'SELECT',
            'select'       => $this->selectBuild(),
            'tablePrefix'  => 'FROM',
            'table'        => $this->tableBuild(),
            'join'         => $this->joinBuild(),
            'where'        => $this->whereBuild(),
            'group'        => $this->groupBuild(),
            'having'       => $this->havingBuild(),
            'order'        => $this->orderBuild(),
            'limitOffset'  => $this->limitOffsetBuild(),
        ];

        if(sizeof($deny))
            $build = array_diff_key($build, array_flip($deny));

        return implode(' ', array_filter($build));
    }

    /**
     * getReadQueryRaw
     *
     * @return string
     */
    public function getReadQueryRaw($deny = []){
        return vsprintf(str_replace('?', '%s', $this->getReadQuery($deny)), array_map(function($item){ return $this->quote($item); }, $this->getReadParams()));
    }

    /**
     * cache
     *
     * @param int $timeout
     * @return $this
     */
    public function cache(int $timeout = null,string $cacheType='file'){
        if($cacheType=='file'){
	        $params['cacheDir']= 'db'.DS;
        }
        $params['cacheTime']=$timeout?$timeout:600;
        $this->cache=new Cache($cacheType,$params);
        $this->cacheType=$cacheType;
        return $this;
    }
    // 记录 sql 运行过程
    protected function trace($res, $startTime, $sql = null)
    {
        if ($sql === null) {
            $sql = $this->sql;
        }

        $sqlRec = [];
        $sqlRec[0] = $res != FALSE ? 'success' : 'false';
        $sqlRec[1] = $sql;
        $sqlRec[2] = round((microtime(true) - $startTime) * 1000, 2);
        $sqlRec[3] = $res ? '' : $this->pdo->errorInfo()[2];;
        $GLOBALS['traceSql'][] = $sqlRec;
    }
    
    /**
     * getReadHash
     *
     * @return string
     */
    public function getReadHash(){
        return md5(implode(func_get_args()));
    }

    /**
     * readQuery
     *
     * @param string $fetch
     * @param void $fetchMode
     * @return mixed
     */
    public function readQuery($fetch = 'fetch', $fetchMode = null){

	    $startTime = microtime(true);//追踪添加

        //if(!$fetchMode && $this->fetchMode) $fetchMode = $this->fetchMode;
        if($fetchMode===null && $this->fetchMode) $fetchMode = $this->fetchMode;

        if($this->pager){
            if($totalRecord = $this->pagerRows ? $this->pagerRows : $this->pdo->query(str_replace('SELECT', 'SELECT COUNT(*)', $this->getReadQueryRaw(['select', 'limitOffset', 'order'])))->fetchColumn()){
                $this->pagerData = [
                    'count'   => $totalRecord,
                    'limit'   => $this->limit,
                    'offset'  => $this->offset,
                    'total'   => ceil($totalRecord / $this->limit),
                    'current' => $this->pager
                ];
            }
        }
            
        $query  = $this->getReadQuery();
        $params = $this->getReadParams();
        //读取缓存
        $hash   = $this->getReadHash($query, join((array)$params), $fetch, $fetchMode);

		if($this->cache && $cached = $this->cache->get($hash)){
			$this->killQuery($query, $params, sizeof((array)$cached), $this->cacheType);
			return $cached;
		}

        // SQL Query
        $runQuery = $this->pdo->prepare($query);
        if($runQuery->execute($params)){
            $results = call_user_func_array([$runQuery, $fetch], [$fetchMode]);
            if(sizeof($this->joinNodes))
                $results = $this->nodeParser($results);
            $results = $this->toJson ? json_encode($results) : $results;
			//设定缓存
            if($this->cache){
	            $this->cache->set($hash,$results);
            }
            $this->killQuery($query, $params, $runQuery->rowCount(), 'mysql');
            $this->trace($results, $startTime, $query);//追踪添加
            return $results;
        }
    }

    /**
     * total
     *
     * @param mixed $table
     */
    public function total($table = null){
        if(!is_null($table)) 
            $this->table($table);
        return $this->value('COUNT(*)');
    }   
    
    /**
     * get
     *
     * @param mixed $table
     * @return void
     */
    public function get($table = null){
        if(!is_null($table)) 
            $this->table($table);
        return $this->readQuery('fetchAll');
    }
    
    /**
     * first
     *
     * @param mixed $table
     * @return void
     */
    public function first($table = null){
        if(!is_null($table)) 
            $this->table($table);
        return $this->readQuery('fetch');
    }
            
    /**
     * value
     *
     * @param mixed $column
     * @return void
     */
    public function value($column){
    	$this->selectFlush($column);
    	return $this->readQuery('fetchColumn', 0);
    }

    /**
     * value
     *
     * @param mixed $column
     * @return void
     */
    //public function value1($column){
    //    $data = $this->limit(1)->column($column);
    //    if (is_array($data) && isset($data[0])) {
    //        return $data[0];
    //    }
    //    return false;
    //}

    /**
     * column/pluck
     *
     * @param mixed $table
     * @return void
     */
    public function column($value, $key = null){
        if (!is_null($key)) {
            $this->selectFlush(implode(', ', [$key, $value]));
            $data = $this->readQuery('fetchAll');
            if (is_array($data) || is_object($data)) {
                return array_column($data, $value, $key);
            } else {
                return $data;
            }
        } else {
            $this->selectFlush($value);
            return $this->readQuery('fetchAll', PDO::FETCH_COLUMN);
        }
    }

    /**
     * toArray
     *
     * @return $this
     */
    public function toArray(){
        $this->fetchMode = PDO::FETCH_ASSOC;
        return $this;
    }

    /**
     * toObject
     *
     * @return $this
     */
    public function toObject(){
        $this->fetchMode = PDO::FETCH_OBJ;
        return $this;
    }

    /**
     * toJson
     *
     * @return $this
     */
    public function toJson(){
        $this->toJson = true;
        return $this;
    }

    /**
     * find
     *
     * @param mixed $value
     */
    public function find($value, $table = null){
        if(!is_null($table)) 
            $this->table($table);
        return $this->where($this->getPrimary($table), $value)->first();
    }

    /**
     * validate
     *
     * @return $this
     */
    public function validate(){
        $this->filter(true);
        return $this;
    }

    /**
     * filter
     *
     * @param bool $forceValid
     * @return $this
     */
    public function filter($forceValid = false){
        $this->isFilter = true;
        if($forceValid)
            $this->isFilterValid = true;
        return $this;
    }
    
    /**
     * filterData
     *
     * @param string $table
     * @param array $insertData
     * @param bool $forceValid
     * @return array
     */
    public function filterData($table, $insertData, $forceValid = false){

        $filtered = [];
        $isBatchData = is_array(reset($insertData));
        $tableStructure = $this->showTable($table);

        if(!$isBatchData)
            $insertData = [$insertData];

        foreach($insertData as $key => $data){
            if(!$forceValid):
                $filtered[$key] = array_intersect_key($data, $tableStructure);
            else:
                foreach($tableStructure as $structure){
                    
                    // fill default
                    if((!is_null($structure['default']) && $structure['default'] != 'current_timestamp()') && (!isset($data[$structure['field']]) || is_null($data[$structure['field']]) || empty($data[$structure['field']])))
                        $data[$structure['field']] = $structure['default'];
                    
                    // not null
                    if(!$structure['extra'] && !$structure['null'] && (!isset($data[$structure['field']]) || is_null($data[$structure['field']])))
                        throw new Exception($structure['field'] . ' Not Null olarak tanımlanmış.');
                        
                    // enum
                    if(strpos($structure['type'], 'enum') !== false):
                        if(isset($data[$structure['field']])):
                            preg_match_all("/'(.*?)'/", $structure['type'], $enumArray);
                            if(!in_array($data[$structure['field']], $enumArray[1])):
                                throw new Exception($structure['field'] . ' için geçerli bir veri girilmedi.');
                            endif;
                        endif;
                    endif;
                    
                    // trim
                    if(isset($data[$structure['field']]))
                        $filtered[$key][$structure['field']] = $data[$structure['field']];
                }
            endif;
        }
        return !$isBatchData ? reset($filtered) : array_values($filtered);
    }

    /**
     * insert
     *
     * @param array $insertData
     * @param string $table
     * @param string $type
     * @return int|bool
     */
    public function insert($insertData, $table = null, $type = 'INSERT'){

        $typeList = ['INSERT', 'INSERT IGNORE', 'INSERT OR IGNORE', 'REPLACE', 'DUPLICATE'];
        
        if(!in_array($type, $typeList) || !is_array($insertData) || !count($insertData))
            return false;

        if(!is_null($table)) 
            $this->table($table);

        if($this->isFilter)
            $insertData = $this->filterData($this->tableBuild(), $insertData, $this->isFilterValid);

        if($insertData){

            if(!is_array(reset($insertData)))
                $insertData = [$insertData];

            $columnList = implode(',', array_keys($insertData[0]));
            $markerList = $this->createMarker($insertData);
            $valuesList = [];
            array_walk_recursive($insertData, function($val, $key) use (&$valuesList){
                $valuesList[] = $val;
            });

            if($type == 'DUPLICATE'):
                $query = "INSERT INTO {$this->tableBuild()} ({$columnList}) VALUES {$markerList} ON DUPLICATE KEY UPDATE {$this->createMarkerWithKey($insertData, '%key=VALUES(%key)')}";
            else:
                $query = "{$type} INTO {$this->tableBuild()} ({$columnList}) VALUES {$markerList}";
            endif;

            $runQuery = $this->pdo->prepare($query);

            if($runQuery->execute($valuesList))
                $this->killQuery($query, $insertData, $runQuery->rowCount());
                return $this->pdo->lastInsertId();
        }
        $this->init();
    }

    /**
     * insertReplace
     *
     * @param array $insertData
     * @param string $table
     * @return int|bool
     */
    public function insertReplace($insertData, $table = null){
        return $this->insert($insertData, $table, 'REPLACE');
    }

    /**
     * insertIgnore
     *
     * @param array $insertData
     * @param string $table
     * @return int|bool
     */
    public function insertIgnore($insertData, $table = null){
        return $this->insert($insertData, $table, $this->getQueryForDriver('insertIgnore'));
    }
    
    /**
     * upsert
     *
     * @param array $insertData
     * @param string $table
     * @return int|bool
     */
    public function upsert($insertData, $table = null){
        return $this->insert($insertData, $table, 'DUPLICATE');
    }
        
    /**
     * update
     *
     * @param array $data
     * @param string $table
     * @return int|bool
     */
    public function update($data, $table = null){
        
        if(!$data)
            return false;

        if(!is_null($table)) 
            $this->table($table);

        if($this->isFilter)
            $data = $this->filterData($this->tableBuild(), $data, $this->isFilterValid);

        if($data){
            $query = "UPDATE {$this->tableBuild()} SET {$this->createMarkerWithKey($data)} {$this->whereBuild()}";
            $runQuery = $this->pdo->prepare($query);
            if($runQuery->execute(array_merge(array_values($data), $this->whereParams)))
                $this->killQuery($query, $data, $runQuery->rowCount());
                return $this->rowCount;
        }
        return false;
    }
    
    /**
     * touch
     *
     * @param string $column
     * @param string $table
     * @return int|bool
     */
    public function touch($column){
        return $this->query("UPDATE {$this->tableBuild()} SET {$column} = !{$column} {$this->whereBuild()}", $this->whereParams)->exec();
    }

    /**
     * increment
     *
     * @param string $column
     * @param int $count
     * @return int|bool
     */
    public function inc($column, int $count = 1, $operator = '+'){
        return $this->query("UPDATE {$this->tableBuild()} SET {$column} = {$column} {$operator} {$count} {$this->whereBuild()}", $this->whereParams)->exec();
    }

    /**
     * decrement
     *
     * @param string $column
     * @param int $count
     * @return int|bool
     */
    public function dec($column, int $count = 1){
        return $this->inc($column, $count, '-');
    }
    
    /**
     * delete
     *
     * @param string $table
     * @return int|bool
     */
    public function delete($table = null){
        
        if(!is_null($table)) 
            $this->table($table);
        
        $query = "DELETE FROM {$this->tableBuild()} {$this->whereBuild()}";

        $runQuery = $this->pdo->prepare($query);

        if($runQuery->execute($this->whereParams))
            $this->killQuery($query, $this->whereParams, $runQuery->rowCount());
            return $this->rowCount;

        return false;
    }

            
    /**
     * runStructureTool
     *
     * @param  string $type
     * @param  string $table
     * @return string|bool
     */
    protected function runStructureTool($type, $table = null){

        if(!is_null($table)) 
            $this->table($table);

        $query = "{$type} TABLE {$this->tableBuild()}";

        if($runQuery = $this->pdo->query($query)){
            $this->killQuery($query);
            return $query;
        }
        return false;
    }
    public function truncate($table = null){
        return $this->runStructureTool('TRUNCATE', $table);
    }
    public function drop($table = null){
        return $this->runStructureTool('DROP', $table);
    }
    public function optimize($table = null){
        return $this->runStructureTool('OPTIMIZE', $table);
    }
    public function analyze($table = null){
        return $this->runStructureTool('ANALYZE', $table);
    }
    public function check($table = null){
        return $this->runStructureTool('CHECK', $table);
    }
    public function checksum($table = null){
        return $this->runStructureTool('CHECKSUM', $table);
    }
    public function repair($table = null){
        return $this->runStructureTool('REPAIR', $table);
    }
    
    /**
     * showTable
     *
     * @param string $table
     * @return array
     */
    public function showTable($table = null){
        if(!is_null($table)) 
            $this->table($table);
        $columns = $this->pdo->query($this->getQueryForDriver('getColumns'))->fetchAll(2);
        return call_user_func(array($this, $this->config['driver'] . 'ColParser'), $columns);
    }

    /**
     * getPrimary
     *
     * @param string $table
     * @return void
     */
    public function getPrimary($table = null){
        if(!is_null($table)) 
            $this->table($table);
        return $this->pdo->query($this->getQueryForDriver('getPrimary'))->fetchColumn();
    }
    
    /**
     * getQuery
     *
     * @param mixed $query
     * @return string
     */
    private function getQueryForDriver($query){
        $queries = [
            'mysql' => [
                'getColumns'   => "SHOW COLUMNS FROM {$this->table}",
                'getPrimary'   => "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = '{$this->table}' AND CONSTRAINT_NAME = 'PRIMARY'",
                'insertIgnore' => 'INSERT IGNORE'
            ],
            'sqlite' => [
                'getColumns'   => "PRAGMA table_info({$this->table})",
                'getPrimary'   => "SELECT t.name FROM pragma_table_info('{$this->table}') as t WHERE t.pk = 1",
                'insertIgnore' => 'INSERT OR IGNORE'
            ]
        ];
        return $queries[$this->config['driver']][$query];
    }
    
    /**
     * sqliteColParser
     *
     * @param mixed $columns
     * @return array
     */
    private function sqliteColParser($columns){
        $parse = [];
        foreach($columns as $col){
            $parse[$col['name']] = [
                'field'   => $col['name'],
                'type'    => $col['type'],
                'null'    => !$col['notnull'],
                'default' => $col['dflt_value'],
                'primary' => $col['pk'],
                'extra'   => $col['pk']
            ];
        }
        return $parse;
    }
    
    /**
     * mysqlColParser
     *
     * @param mixed $columns
     * @return array
     */
    private function mysqlColParser($columns){
        $parse = [];
        foreach($columns as $col){
            $parse[$col['Field']] = [
                'field'   => $col['Field'],
                'type'    => $col['Type'],
                'null'    => $col['Null'] !== 'NO' ? true : false,
                'default' => $col['Default'],
                'primary' => $col['Key'] === 'PRI' ? true : false,
                'extra'   => $col['Extra']
            ];
        }
        return $parse;
    }
    
    /**
     * inTransaction
     *
     * @return void
     */
    public function inTransaction(){
        return $this->pdo->inTransaction();
    }   

    /**
     * beginTransaction
     *
     * @return void
     */
    public function beginTransaction(){
        if(!$this->pdo->inTransaction())
            $this->pdo->beginTransaction();
    } 

    /**
     * commit
     *
     * @return void
     */
    public function commit(){
        if($this->pdo->inTransaction())
            $this->pdo->commit();
    }
            
    /**
     * rollBack
     *
     * @return void
     */
    public function rollBack(){
        if($this->pdo->inTransaction())
            $this->pdo->rollBack();
    }
    
    /**
     * quote
     *
     * @param mixed $data
     * @return string
     */
    public function quote($data)
    {
        return $data === null ? 'NULL' : (
            is_int($data) || is_float($data) ? $data : $this->pdo->quote($data)
        );
    }
    
    /**
     * lastInsertId
     *
     * @return int
     */
    public function lastInsertId(){
        return $this->pdo->lastInsertId();
    }
    
    /**
     * rowCount
     *
     * @return int
     */
    public function rowCount(){
        return $this->rowCount;
    }
    
    /**
     * queryCount
     *
     * @return int
     */
    public function queryCount(){
        return sizeof($this->queryHistory);
    }
    
    /**
     * queryHistory
     *
     * @return array
     */
    public function queryHistory(){
        return $this->queryHistory;
    }
    
    /**
     * lastQuery
     *
     * @param bool $withParams
     * @return string|array
     */
    public function lastQuery($withParams = false){
        if($this->queryHistory)
            return $withParams ? end($this->queryHistory) : end($this->queryHistory)['query'];
    }

    /**
     * lastParams
     *
     * @return array
     */
    public function lastParams(){
        return $this->queryCount() ? end($this->queryHistory)['params'] : false;
    }
    
    /**
     * addQueryHistory
     *
     * @param string $query
     * @param string|array $params
     * @return array
     */
    public function addQueryHistory($query, $params = null, $rowCount = 0, $from = null){
        return $this->queryHistory[] = [
            'query'  => $query,
            'params' => $params,
            'count'  => $rowCount,
            'from'   => $from,
        ];
    }
    
    /**
     * killQuery
     *
     * @param string $query
     * @param string|array $params
     * @return void
     */
    public function killQuery($query, $params = null, $rowCount = 0, $from = null){
        $this->addQueryHistory($query, $params, $rowCount, $from);
        $this->init();
        if($rowCount) $this->rowCount = $rowCount;
    }
    
    /**
     * close
     *
     * @return void
     */
    public function close(){
        $this->pdo = null;
    }
}

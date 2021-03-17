<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Core;
use Startmvc\Lib\Http\Request;

abstract class Controller extends Start
{
	public $assign=[];
    /**
     * 为模板对象赋值
     */
    public function assign($name, $data=null)
    {
	    if(is_array($name)){
		    $this->assign = $name;
	    }else{
		    $this->assign[$name] = $data;
	    }
	    //print_r($this->assign);
    }


    protected function view($template = '')
    {
        if (is_array($template)) {
            $template = APP_PATH . '/' . $template[0] . '/View/' . $template[1] . '.php';
        } else {
            if ($template == '') {
                $template = CONTROLLER . '_' . ACTION;
            }
            $template = APP_PATH . '/' . (MODULE != '' ? MODULE . '/' : '') . 'View/' . $template . '.php';
        }
        if (file_exists($template)) {
            $contents = file_get_contents($template);
            $contents = $this->tp_engine($contents);

        header('Content-Type:text/html; charset=utf-8');
        $this->show($contents);
        } else {
            $this->content('视图文件不存在：' . $template);
        }
    }
    private function tp_engine($c)
    {
        preg_match_all('/{include (.+)}/Ui', $c, $include);
        foreach ($include[1] as $inc) {
            $inc_array = explode('|', $inc);
            if (isset($inc_array[1])) {
                $inc_file = APP_PATH . '/' . $inc_array[1] . '/View/' . $inc_array[0] . '.php';
            } else {
                $inc_file = APP_PATH . '/' . (MODULE != '' ? MODULE . '/' : '') . '/View/' . $inc_array[0] . '.php';
            }
            $inc_content = file_get_contents($inc_file);
            $c = str_replace('{include ' . $inc . '}', $inc_content, $c);
        }
        $c = str_replace('<?=', '<?php echo ', $c);
        $c = str_replace('<?', '<?php ', $c);
        $c = str_replace('<?php php', '<?php', $c);
        return $c;
    }
    protected function show($content)
    {
        $file_name = md5(mb_convert_encoding(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '', 'UTF-8', 'GBK'));
        $runtime_file = TEMP_PATH . $file_name . '.php';
        $of = fopen($runtime_file, 'w+');
        fwrite($of, $content);
        fclose($of);
        if(is_object($this->assign)) {
			extract((array)$this->assign);
	    }else{
			extract($this->assign);
	    }
        header('Content-Type:text/html; charset=utf-8');
        include_once ($runtime_file);
    }
    public function content($content)
    {
        header('Content-Type:text/plain; charset=utf-8');
        echo $content;
    }
    protected function success($options = [])
    {
        $msg = isset($options['msg']) ? $options['msg'] : '';
        $url = isset($options['url']) ? $options['url'] : '';
        if (isset($options['type'])) {
            $type = $options['type'];
        } else {
            if (Request::isAjax()) {
                $type = 'json';
            } else {
                $type = 'html';
            }
        }
        if ($type == 'json') {
            $this->json([
                'results' => 'success',
                'msg' => $msg,
                'url' => $url
            ]);
        } else {
            $results = 'success';
            include '../startmvc/Core/location.php';
            exit();
        }
    }
    protected function error($options = [])
    {
        $msg = isset($options['msg']) ? $options['msg'] : '';
        $url = isset($options['url']) ? $options['url'] : '';
        if (isset($options['type'])) {
            $type = $options['type'];
        } else {
            if (Request::isAjax()) {
                $type = 'json';
            } else {
                $type = 'html';
            }
        }
        if ($type == 'json') {
            $this->json([
                'results' => 'error',
                'msg' => $msg,
                'url' => $url
            ]);
        } else {
            $results = 'error';
            include '../startmvc/Core/location.php';
            exit();
        }
    }
    protected function json($data)
    {
        header('Content-Type:application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    protected function redirect($url)
    {
        header('location:' . $url);
        exit();
    }
    protected function notFound()
    {
        header("HTTP/1.1 404 Not Found");  
        header("Status: 404 Not Found");
    }
    public function __call($fun, $arg)
    {
        $this->notFound();
    }
}

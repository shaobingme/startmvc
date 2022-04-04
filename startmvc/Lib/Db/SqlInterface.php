<?php

/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

namespace Startmvc\Lib\Db;
interface SqlInterface
{
    /**
     * @param null $type
     * @param null $argument
     *
     * @return mixed
     */
    public function get($type = null, $argument = null);

    /**
     * @param null $type
     * @param null $argument
     *
     * @return mixed
     */
    public function getAll($type = null, $argument = null);

    /**
     * @param array $data
     * @param bool  $type
     *
     * @return mixed
     */
    public function update(array $data, $type = false);

    /**
     * @param array $data
     * @param bool  $type
     *
     * @return mixed
     */
    public function insert(array $data, $type = false);

    /**
     * @param bool $type
     *
     * @return mixed
     */
    public function delete($type = false);
}

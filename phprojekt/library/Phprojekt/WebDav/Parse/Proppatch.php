<?php
/**
 * Helper class for parsing PROPPATCH request bodies.
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Core
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0.3
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */

/**
 * Helper class for parsing PROPPATCH request bodies.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Core
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0.3
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */
class Phprojekt_WebDav_Parse_Proppatch extends Phprojekt_WebDav_Parse_Abstract
{
    /**
     * Tag mode.
     *
     * @var string
     */
    public $mode = null;

    /**
     * Current prop.
     *
     * @var array
     */
    public $current = array();

    /**
     * Set the data handler.
     *
     * @return void
     */
    public function setCharacterDataHandler()
    {
        xml_set_character_data_handler($this->xmlParser, array(&$this, "data"));
    }

    /**
     * Start tag handler.
     *
     * @return void
     */
    public function startElement()
    {
        $args = func_get_args();
        $name = (isset($args[1])) ? $args[1] : '';

        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "")
                $this->success = false;
        } else {
            $ns  = "";
            $tag = $name;
        }

        if ($this->depth == 1) {
            $this->mode = $tag;
        }

        if ($this->depth == 3) {
            $prop = array("name" => $tag);
            $this->current = array("name"   => $tag,
                                   "ns"     => $ns,
                                   "status" => 200);
            if ($this->mode == "set") {
                // Default set val
                $this->current["val"] = "";
            }
        }

        if ($this->depth >= 4) {
            $this->current["val"] .= "<" . $tag;
            if (isset($attr)) {
                foreach ($attr as $key => $val) {
                    $this->current["val"] .= ' ' . $key . '="' . str_replace('"', '&quot;', $val) . '"';
                }
            }
            $this->current["val"] .= ">";
        }

        $this->depth++;
    }

    /**
     * Input data handler.
     *
     * @return void
     */
    public function data()
    {
        $args = func_get_args();
        $name = (isset($args[1])) ? $args[1] : '';

        if (isset($this->current)) {
            $this->current["val"] .= $data;
        }
    }

    /**
     * End tag handler.
     *
     * @return void
     */
    public function endElement()
    {
        $args = func_get_args();
        $name = (isset($args[1])) ? $args[1] : '';

        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "")
                $this->success = false;
        } else {
            $ns  = "";
            $tag = $name;
        }

        $this->depth--;

        if ($this->depth >= 4) {
            $this->current["val"] .= "</" . $tag . ">";
        }

        if ($this->depth == 3) {
            if (isset($this->current)) {
                $this->props[] = $this->current;
                unset($this->current);
            }
        }
    }
}

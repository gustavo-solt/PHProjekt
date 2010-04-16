<?php
/**
 * Helper class for parsing PROPFIND request bodies.
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
 * Helper class for parsing PROPFIND request bodies.
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
class Phprojekt_WebDav_Parse_Propfind extends Phprojekt_WebDav_Parse_Abstract
{
    /**
     * Constructor.
     *
     * @param string $path Path of input stream.
     *
     * @return void
     */
    public function __construct($path)
    {
        parent::__construct($path);

        // If no input was parsed it was a request
        if (!count($this->props)) {
            $this->props = "all"; // default
        }
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

        // Name space handling
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "") {
                $this->success = false;
            }
        } else {
            $ns  = "";
            $tag = $name;
        }

        // Special tags at level 1: <allprop> and <propname>
        if ($this->depth == 1) {
            if ($tag == "allprop") {
                $this->props = "all";
            }

            if ($tag == "propname") {
                $this->props = "names";
            }
        }

        // Requested properties are found at level 2
        if ($this->depth == 2) {
            $prop = array("name" => $tag);
            if ($ns) {
                $prop["xmlns"] = $ns;
            }
            $this->props[] = $prop;
        }

        // Increment depth count
        $this->depth++;
    }

    /**
     * End tag handler.
     *
     * @return void
     */
    public function endElement()
    {
        // Here we only need to decrement the depth count
        $this->depth--;
    }
}

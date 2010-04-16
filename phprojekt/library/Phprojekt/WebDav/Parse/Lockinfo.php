<?php
/**
 * Helper class for parsing LOCK request bodies.
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
 * Helper class for parsing LOCK request bodies.
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
class Phprojekt_WebDav_Parse_Lockinfo extends Phprojekt_WebDav_Parse_Abstract
{
     /**
     * lock type, currently only "write"
     *
     * @var string
     * @access public
     */
    var $locktype = "";

    /**
     * Lock scope, "shared" or "exclusive".
     *
     * @var string
     */
    public $lockscope = "";

    /**
     * Lock owner information.
     *
     * @var string
     */
    public $owner = "";

    /**
     * Flag that is set during lock owner read.
     *
     * @var boolean
     */
    private $collectOwner = false;

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

        // Check if required tags where found
        $this->success &= !empty($this->locktype);
        $this->success &= !empty($this->lockscope);
    }

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

        // Namespace handling
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
        } else {
            $ns  = "";
            $tag = $name;
        }

        if ($this->collectOwner) {
            // Everything within the <owner> tag needs to be collected
            $nsShort = "";
            $nsAttr  = "";
            if ($ns) {
                if ($ns == "DAV:") {
                    $nsShort = "D:";
                } else {
                    $nsAttr = " xmlns='" . $ns . "'";
                }
            }
            $this->owner .= "<" . $nsShort . $tag . $nsAttr . ">";
        } else if ($ns == "DAV:") {
            // Parse only the essential tags
            switch ($tag) {
                case "write":
                    $this->locktype = $tag;
                    break;
                case "exclusive":
                case "shared":
                    $this->lockscope = $tag;
                    break;
                case "owner":
                    $this->collectOwner = true;
                    break;
            }
        }
    }

    /**
     * Data handler.
     *
     * @return void
     */
    public function data()
    {
        $args = func_get_args();
        $data = (isset($args[1])) ? $args[1] : '';

        // Only the <owner> tag has data content
        if ($this->collectOwner) {
            $this->owner .= $data;
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

        // Namespace handling
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
        } else {
            $ns  = "";
            $tag = $name;
        }

        // <owner> finished?
        if (($ns == "DAV:") && ($tag == "owner")) {
            $this->collectOwner = false;
        }

        // Within <owner> we have to collect everything
        if ($this->collectOwner) {
            $nsShort = "";
            $nsAttr  = "";
            if ($ns) {
                if ($ns == "DAV:") {
                    $nsShort = "D:";
                } else {
                    $nsAttr = " xmlns='" . $ns . "'";
                }
            }
            $this->owner .= "</" . $nsShort . $tag . $nsAttr . ">";
        }
    }
}

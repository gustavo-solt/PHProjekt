<?php
/**
 * Abstract Helper class for parsing request bodies.
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
 * Abstract Helper class for parsing request bodies.
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
class Phprojekt_WebDav_Parse_Abstract
{
    /**
     * Success state flag.
     *
     * @var boolean
     */
    public $success = false;

    /**
     * Found properties are collected here.
     *
     * @var array
     */
    public $props = array();

    /**
     * Internal tag nesting depth counter.
     *
     * @var integer
     */
    public $depth = 0;

    /**
     * xml Parser.
     *
     * @var xml_parser_create_ns
     */
    public $xmlParser = null;

    /**
     * Constructor.
     *
     * @param string $path Path of input stream.
     *
     * @return void
     */
    public function __construct($path)
    {
        // Success state flag
        $this->success = true;

        // Property storage array
        $this->props = array();

        // Internal tag depth counter
        $this->depth = 0;

        // Remember if any input was parsed
        $hadInput = false;

        // Open input stream
        $fileIn = fopen($path, "r");
        if (!$fileIn) {
            $this->success = false;
            return;
        }

        // Create XML parser
        $this->xmlParser = xml_parser_create_ns("UTF-8", " ");
        $this->setElementHandler();
        $this->setCharacterDataHandler();
        $this->setOptions();

        // Parse input
        while ($this->success && !feof($fileIn)) {
            $line = fgets($fileIn);
            if (is_string($line)) {
                $hadInput       = true;
                $this->success &= xml_parse($this->xmlParser, $line, false);
            }
        }

        // Finish parsing
        if ($hadInput) {
            $this->success &= xml_parse($this->xmlParser, "", true);
        }

        // Free parser
        xml_parser_free($this->xmlParser);

        // Close input stream
        fclose($fileIn);
    }

    /**
     * Set the start and end tag handler.
     *
     * @return void
     */
    public function setElementHandler()
    {
        // Set tag and data handlers
        xml_set_element_handler($this->xmlParser, array(&$this, "startElement"), array(&$this, "endElement"));
    }

    /**
     * Set the data handler.
     *
     * @return void
     */
    public function setCharacterDataHandler()
    {
    }

    /**
     * Set the xml parser options.
     *
     * @return void
     */
    public function setOptions()
    {
        // We want a case sensitive parser
        xml_parser_set_option($this->xmlParser, XML_OPTION_CASE_FOLDING, false);
    }

    /**
     * Start tag handler.
     *
     * @return void
     */
    public function startElement()
    {
    }

    /**
     * End tag handler.
     *
     * @return void
     */
    public function endElement()
    {
    }
}

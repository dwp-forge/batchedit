<?php

/**
 * Plugin BatchEdit: User request
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditRequest {

    const COMMAND_PREVIEW = 'preview';
    const COMMAND_APPLY = 'apply';

    private $options;
    private $command;
    private $namespace;
    private $regexp;
    private $replacement;
    private $summary;
    private $minorEdit;
    private $appliedMatches;

    /**
     *
     */
    public function __construct() {
        $this->options = array();
        $this->command = $this->parseCommand();
        $this->namespace = $this->parseNamespace();
        $this->regexp = $this->parseRegexp();
        $this->replacement = $this->parseReplacement();
        $this->summary = $this->parseSummary();
        $this->minorEdit = isset($_REQUEST['minor']);

        if ($this->command == self::COMMAND_APPLY) {
            $this->appliedMatches = $this->parseAppliedMatches();
        }
    }

    /**
     *
     */
    public function getCommand() {
        return $this->command;
    }

    /**
     *
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     *
     */
    public function getRegexp() {
        return $this->regexp;
    }

    /**
     *
     */
    public function getReplacement() {
        return $this->replacement;
    }

    /**
     *
     */
    public function getSummary() {
        return $this->summary;
    }

    /**
     *
     */
    public function getMinorEdit() {
        return $this->minorEdit;
    }

    /**
     *
     */
    public function getOption($id) {
        if (array_key_exists($id, $this->options)) {
            return $this->options[$id];
        }

        return NULL;
    }

    /**
     *
     */
    public function getAppliedMatches() {
        return $this->appliedMatches;
    }

    /**
     *
     */
    private function parseCommand() {
        if (!is_array($_REQUEST['cmd'])) {
            throw new Exception('err_invreq');
        }

        $command = key($_REQUEST['cmd']);

        if (($command != 'preview') && ($command != 'apply')) {
            throw new Exception('err_invreq');
        }

        return $command;
    }

    /**
     *
     */
    private function parseNamespace() {
        if (!isset($_REQUEST['namespace'])) {
            throw new Exception('err_invreq');
        }

        $namespace = trim($_REQUEST['namespace']);

        if ($namespace != '') {
            global $ID;

            $namespace = resolve_id(getNS($ID), $namespace . ':');

            if ($namespace != '') {
                $namespace .= ':';
            }
        }

        return $namespace;
    }

    /**
     *
     */
    private function parseRegexp() {
        if (!isset($_REQUEST['search']) || !isset($_REQUEST['searchmode'])) {
            throw new Exception('err_invreq');
        }

        $this->setOption('searchmode');

        $regexp = trim($_REQUEST['search']);

        if ($regexp == '') {
            throw new Exception('err_noregexp');
        }

        if ($_REQUEST['searchmode'] == 'regexp') {
            if (preg_match('/^([^\w\\\\]|_).+?\1[imsxeADSUXJu]*$/', $regexp) != 1) {
                throw new Exception('err_invregexp');
            }
        }
        else {
            $regexp = '/' . preg_quote($regexp, '/') . '/';
        }

        return $regexp;
    }

    /**
     *
     */
    private function parseReplacement() {
        if (!isset($_REQUEST['replace'])) {
            throw new Exception('err_invreq');
        }

        $unescape = function($matches) {
            if (strlen($matches[1]) % 2) {
                $unescaped = array('n' => "\n", 'r' => "\r", 't' => "\t");

                return substr($matches[1], 1) . $unescaped[$matches[2]];
            }
            else {
                return $matches[0];
            }
        };

        return preg_replace_callback('/(\\\\+)([nrt])/', $unescape, $_REQUEST['replace']);
    }

    /**
     *
     */
    private function parseSummary() {
        if (!isset($_REQUEST['summary'])) {
            throw new Exception('err_invreq');
        }

        return $_REQUEST['summary'];
    }

    /**
     *
     */
    private function parseAppliedMatches() {
        if (!isset($_REQUEST['apply'])) {
            return array();
        }

        if (!is_array($_REQUEST['apply'])) {
            throw new Exception('err_invcmd');
        }

        return array_keys($_REQUEST['apply']);
    }

    /**
     *
     */
    private function setOption($id) {
        $this->options[$id] = $_REQUEST[$id];
    }
}

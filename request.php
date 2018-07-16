<?php

/**
 * Plugin BatchEdit: User request
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditRequest {

    const COMMAND_WELCOME = 'welcome';
    const COMMAND_PREVIEW = 'preview';
    const COMMAND_APPLY = 'apply';

    private $command;
    private $options;
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
        $this->command = $this->parseCommand();

        if ($this->command == self::COMMAND_WELCOME) {
            return;
        }

        $this->options = $this->parseOptions();
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
    public function getOption($id) {
        if (array_key_exists($id, $this->options)) {
            return $this->options[$id];
        }

        return NULL;
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
    public function getAppliedMatches() {
        return $this->appliedMatches;
    }

    /**
     *
     */
    private function parseCommand() {
        if (!isset($_REQUEST['cmd'])) {
            return self::COMMAND_WELCOME;
        }

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
    private function parseOptions() {
        if (!isset($_REQUEST['searchmode'])) {
            throw new Exception('err_invreq');
        }

        $options = array();

        $options['searchmode'] = $_REQUEST['searchmode'];
        $options['matchcase'] = isset($_REQUEST['matchcase']);
        $options['multiline'] = isset($_REQUEST['multiline']);

        return $options;
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
        if (!isset($_REQUEST['search'])) {
            throw new Exception('err_invreq');
        }

        $regexp = trim($_REQUEST['search']);

        if ($regexp == '') {
            throw new Exception('err_nosearch');
        }

        if ($this->getOption('searchmode') == 'regexp') {
            if (preg_match('/^([^\w\\\\]|_).+?\1[imsxeADSUXJu]*$/s', $regexp) != 1) {
                throw new Exception('err_invregexp');
            }
        }
        else {
            $regexp = '/' . preg_quote($regexp, '/') . '/';
        }

        $regexp = str_replace("\r\n", "\n", $regexp);

        if (!$this->getOption('matchcase')) {
            $regexp .= 'i';
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

        $replace = str_replace("\r\n", "\n", $_REQUEST['replace']);

        $unescape = function($matches) {
            static $unescaped = array('n' => "\n", 'r' => "\r", 't' => "\t");

            if (strlen($matches[1]) % 2) {
                return substr($matches[1], 1) . $unescaped[$matches[2]];
            }
            else {
                return $matches[0];
            }
        };

        return preg_replace_callback('/(\\\\+)([nrt])/', $unescape, $replace);
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

        $matchIds = json_decode($_REQUEST['apply']);

        if (!is_array($matchIds)) {
            throw new Exception('err_invreq');
        }

        return $matchIds;
    }
}

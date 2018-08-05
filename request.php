<?php

/**
 * Plugin BatchEdit: User request
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'batchedit/engine.php');

class BatcheditRequest {

    const COMMAND_WELCOME = 'welcome';
    const COMMAND_PREVIEW = 'preview';
    const COMMAND_APPLY = 'apply';

    private $command;
    private $sessionId;
    private $namespace;
    private $regexp;
    private $replacement;
    private $summary;
    private $minorEdit;
    private $appliedMatches;

    /**
     *
     */
    public function __construct($config) {
        $this->command = $this->parseCommand();

        if ($this->command == self::COMMAND_WELCOME) {
            return;
        }

        $config->update($this->parseOptions());

        $this->sessionId = $this->parseSessionId();
        $this->namespace = $this->parseNamespace();
        $this->regexp = $this->parseRegexp($config);
        $this->replacement = $this->parseReplacement();
        $this->summary = $this->parseSummary();
        $this->minorEdit = isset($_REQUEST['minor']);

        if ($this->command == self::COMMAND_APPLY || $config->getConf('keepmarks')) {
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
    public function getSessionId() {
        return $this->sessionId;
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
            throw new BatcheditException('err_invreq');
        }

        $command = key($_REQUEST['cmd']);

        if (($command != 'preview') && ($command != 'apply')) {
            throw new BatcheditException('err_invreq');
        }

        return $command;
    }

    /**
     *
     */
    private function parseOptions() {
        if (!isset($_REQUEST['searchmode'])) {
            throw new BatcheditException('err_invreq');
        }

        $options = array();

        $options['searchmode'] = $_REQUEST['searchmode'];
        $options['matchcase'] = isset($_REQUEST['matchcase']);
        $options['multiline'] = isset($_REQUEST['multiline']);
        $options['advregexp'] = isset($_REQUEST['advregexp']);
        $options['matchctx'] = isset($_REQUEST['matchctx']);
        $options['ctxchars'] = isset($_REQUEST['ctxchars']) ? $_REQUEST['ctxchars'] : '';
        $options['ctxlines'] = isset($_REQUEST['ctxlines']) ? $_REQUEST['ctxlines'] : '';
        $options['searchlimit'] = isset($_REQUEST['searchlimit']);
        $options['searchmax'] = isset($_REQUEST['searchmax']) ? $_REQUEST['searchmax'] : '';
        $options['keepmarks'] = isset($_REQUEST['keepmarks']);
        $options['markpolicy'] = isset($_REQUEST['markpolicy']) ? $_REQUEST['markpolicy'] : '';
        $options['checksummary'] = isset($_REQUEST['checksummary']);

        return $options;
    }

    /**
     *
     */
    private function parseSessionId() {
        if (!isset($_REQUEST['session'])) {
            return '';
        }

        return $_REQUEST['session'];
    }

    /**
     *
     */
    private function parseNamespace() {
        if (!isset($_REQUEST['namespace'])) {
            throw new BatcheditException('err_invreq');
        }

        $namespace = trim($_REQUEST['namespace']);

        if ($namespace != '') {
            global $ID;

            $namespace = resolve_id(getNS($ID), $namespace . ':') . ':';
        }

        return $namespace;
    }

    /**
     *
     */
    private function parseRegexp($config) {
        if (!isset($_REQUEST['search'])) {
            throw new BatcheditException('err_invreq');
        }

        $regexp = trim($_REQUEST['search']);

        if ($regexp == '') {
            throw new BatcheditException('err_nosearch');
        }

        if ($config->getConf('searchmode') == 'regexp') {
            if ($config->getConf('advregexp')) {
                if (preg_match('/^([^\w\\\\]|_).+?\1[imsxADSUXJu]*$/s', $regexp) != 1) {
                    throw new BatcheditException('err_invregexp');
                }
            }
            else {
                $regexp = "\033" . $regexp . "\033um";
            }
        }
        else {
            $regexp = "\033" . preg_quote($regexp) . "\033";
        }

        $regexp = str_replace("\r\n", "\n", $regexp);

        if (!$config->getConf('matchcase')) {
            $regexp .= 'i';
        }

        return $regexp;
    }

    /**
     *
     */
    private function parseReplacement() {
        if (!isset($_REQUEST['replace'])) {
            throw new BatcheditException('err_invreq');
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
            throw new BatcheditException('err_invreq');
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
            throw new BatcheditException('err_invreq');
        }

        return $matchIds;
    }
}

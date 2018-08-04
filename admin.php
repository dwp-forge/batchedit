<?php

/**
 * Plugin BatchEdit
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'admin.php');
require_once(DOKU_PLUGIN . 'batchedit/config.php');
require_once(DOKU_PLUGIN . 'batchedit/engine.php');
require_once(DOKU_PLUGIN . 'batchedit/interface.php');
require_once(DOKU_PLUGIN . 'batchedit/request.php');

class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    private static $instance = NULL;

    private $command;
    private $config;
    private $session;

    public static function getInstance() {
        return self::$instance;
    }

    public function __construct() {
        $this->command = BatcheditRequest::COMMAND_WELCOME;
        $this->config = new BatcheditConfig();
        $this->session = new BatcheditSession($this->getConf('sessionexp'));

        self::$instance = $this;
    }

    /**
     *
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Handle user request
     */
    public function handle() {
        try {
            $this->handleRequest();
        }
        catch (BatcheditException $error) {
            $this->session->setError($error);
            $this->session->expire();
        }
    }

    /**
     * Output appropriate html
     */
    public function html() {
        $interface = new BatcheditInterface($this);

        $interface->configure($this->config);

        $interface->printBeginning($this->session->getId());
        $interface->printMessages($this->session->getMessages());

        if ($this->session->getMatchCount() > 0) {
            $interface->printTotalStats($this->command, $this->session->getMatchCount(),
                    $this->session->getPageCount(), $this->session->getEditCount());
            $interface->printMatches($this->session->getPages());
        }

        $interface->printMainForm($this->session->getMatchCount() > 0);
        $interface->printEnding();
    }

    /**
     *
     */
    private function handleRequest() {
        $request = new BatcheditRequest($this->config);

        $this->command = $request->getCommand();

        switch ($this->command) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $this->handlePreview($request);
                break;

            case BatcheditRequest::COMMAND_APPLY:
                $this->handleApply($request);
                break;
        }
    }

    /**
     *
     */
    private function handlePreview($request) {
        $engine = $this->createEngine();

        $this->session->setId($request->getSessionId());
        $this->findMatches($engine, $request);
        $this->markMatches($engine, $request);
        $this->session->save($request, $this->config);
    }

    /**
     *
     */
    private function handleApply($request) {
        $engine = $this->createEngine();

        if (!$this->session->load($request, $this->config)) {
            $this->findMatches($engine, $request);
        }

        $this->applyMatches($engine, $request);
        $this->session->save($request, $this->config);
    }

    /**
     *
     */
    private function createEngine() {
        if ($this->getConf('timelimit') > 0) {
            set_time_limit($this->getConf('timelimit'));
        }

        return new BatcheditEngine($this->session);
    }

    /**
     *
     */
    private function findMatches($engine, $request) {
        $engine->findMatches($request->getNamespace(), $request->getRegexp(), $request->getReplacement(),
                $this->config->getConf('searchlimit') ? $this->config->getConf('searchmax') : -1,
                $this->config->getConf('matchctx') ? $this->config->getConf('ctxchars') : 0,
                $this->config->getConf('ctxlines'));
    }

    /**
     *
     */
    private function markMatches($engine, $request) {
        if (!$this->config->getConf('keepmarks') || $this->session->getMatchCount() == 0 || empty($request->getAppliedMatches())) {
            return;
        }

        $engine->markRequestedMatches($request->getAppliedMatches(), $this->config->getConf('markpolicy'));
    }

    /**
     *
     */
    private function applyMatches($engine, $request) {
        if ($this->session->getMatchCount() == 0 || empty($request->getAppliedMatches())) {
            return;
        }

        $engine->markRequestedMatches($request->getAppliedMatches());
        $engine->applyMatches($request->getSummary(), $request->getMinorEdit());
    }
}

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

    private $request;
    private $config;
    private $session;
    private $engine;

    public static function getInstance() {
        return self::$instance;
    }

    public function __construct() {
        $this->request = NULL;
        $this->config = new BatcheditConfig();
        $this->session = new BatcheditSession();
        $this->engine = new BatcheditEngine($this->session);

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
            $interface->printTotalStats($this->request->getCommand(), $this->session->getMatchCount(),
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
        $this->request = new BatcheditRequest($this->config);

        switch ($this->request->getCommand()) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $this->handlePreview();
                break;

            case BatcheditRequest::COMMAND_APPLY:
                $this->handleApply();
                break;
        }
    }

    /**
     *
     */
    private function handlePreview() {
        $this->session->setId($this->request->getSessionId());
        $this->findMatches();
        $this->session->save($this->request, $this->config);
    }

    /**
     *
     */
    private function handleApply() {
        if (!$this->session->load($this->request, $this->config)) {
            $this->findMatches();
        }

        $this->applyMatches();

        if ($this->session->getEditCount() > 0) {
            $this->session->expire();
        }
    }

    /**
     *
     */
    private function findMatches() {
        $this->engine->findMatches(
                $this->request->getNamespace(), $this->request->getRegexp(), $this->request->getReplacement(),
                $this->config->getConf('searchlimit') ? $this->config->getConf('searchmax') : -1);
    }

    /**
     *
     */
    private function applyMatches() {
        if ($this->session->getMatchCount() == 0 || empty($this->request->getAppliedMatches())) {
            return;
        }

        $this->engine->markRequestedMatches($this->request->getAppliedMatches());
        $this->engine->applyMatches($this->request->getSummary(), $this->request->getMinorEdit());
    }
}

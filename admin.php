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

    private $error;
    private $request;
    private $config;
    private $session;
    private $engine;
    private $interface;

    public static function getInstance() {
        return self::$instance;
    }

    public function __construct() {
        $this->error = NULL;
        $this->request = NULL;
        $this->config = new BatcheditConfig();
        $this->session = new BatcheditSession();
        $this->engine = new BatcheditEngine($this->session);
        $this->interface = new BatcheditInterface($this);

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
        catch (Exception $error) {
            $this->error = $error;

            $this->interface->addErrorMessage($error->getMessage());
        }
    }

    /**
     * Output appropriate html
     */
    public function html() {
        $this->interface->configure($this->config);

        $this->interface->printBeginning($this->session->getId());
        $this->interface->printMessages();

        $showMatches = empty($this->error) && $this->session->getMatchCount() > 0;

        if ($showMatches) {
            $this->interface->printTotalStats($this->request->getCommand(), $this->session->getMatchCount(),
                    $this->session->getPageCount(), $this->session->getEditCount());
            $this->interface->printMatches($this->session->getPages());
        }

        $this->interface->printMainForm($showMatches);
        $this->interface->printEnding();
    }

    /**
     *
     */
    private function handleRequest() {
        $this->request = new BatcheditRequest($this->config);

        if ($this->request->getCommand() == BatcheditRequest::COMMAND_WELCOME) {
            // First time - nothing to do
            return;
        }

        if (!$this->session->load($this->request, $this->config)) {
            $this->findMatches();
        }

        if ($this->request->getCommand() == BatcheditRequest::COMMAND_APPLY) {
            $this->applyMatches();
        }

        $this->session->save($this->request, $this->config);
    }

    /**
     *
     */
    private function findMatches() {
        $interrupted = $this->engine->findMatches(
                $this->request->getNamespace(), $this->request->getRegexp(), $this->request->getReplacement(),
                $this->config->getConf('searchlimit') ? $this->config->getConf('searchmax') : 0);

        if ($interrupted) {
            $this->interface->addWarningMessage('war_searchlimit');
        }

        if ($this->session->getMatchCount() == 0) {
            $this->interface->addWarningMessage('war_nomatches');
        }
    }

    /**
     *
     */
    private function applyMatches() {
        if ($this->session->getMatchCount() == 0 || empty($this->request->getAppliedMatches())) {
            return;
        }

        $this->engine->markRequestedMatches($this->request->getAppliedMatches());

        $errors = $this->engine->applyMatches($this->request->getSummary(), $this->request->getMinorEdit());

        foreach ($errors as $pageId => $error) {
            if ($error instanceof BatcheditAccessControlException) {
                $this->interface->addWarningMessage('war_norights', $pageId);
            }
            elseif ($error instanceof BatcheditPageLockedException) {
                $this->interface->addWarningMessage('war_pagelock', $pageId, $error->lockedBy);
            }
        }
    }
}

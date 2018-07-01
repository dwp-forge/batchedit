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
require_once(DOKU_PLUGIN . 'batchedit/engine.php');
require_once(DOKU_PLUGIN . 'batchedit/interface.php');
require_once(DOKU_PLUGIN . 'batchedit/request.php');

class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    private $error;
    private $request;
    private $engine;
    private $interface;

    public function __construct() {
        $this->error = NULL;
        $this->request = NULL;
        $this->engine = NULL;
        $this->interface = new BatcheditInterface($this);
    }

    /**
     * Handle user request
     */
    public function handle() {
        if (!isset($_REQUEST['cmd'])) {
            // First time - nothing to do
            return;
        }

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
        global $ID;

        ptln('<!-- batchedit -->');
        ptln('<div id="batchedit">');

        $this->interface->printMessages();

        ptln('<form action="' . wl($ID) . '" method="post">');

        if (empty($this->error) && !empty($this->request) && $this->engine->getMatchCount() > 0) {
            $this->interface->printTotalStats($this->request->getCommand(), $this->engine->getMatchCount(),
                    $this->engine->getPageCount(), $this->engine->getEditCount());
            $this->interface->printMatches($this->engine->getPages());
        }

        $this->interface->printMainForm($this->getPluginName());

        ptln('</form>');
        ptln('</div>');
        ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    private function handleRequest() {
        $this->request = new BatcheditRequest();
        $this->engine = new BatcheditEngine();

        $matches = $this->engine->findMatches($this->request->getNamespace(), $this->request->getRegexp(), $this->request->getReplacement());

        if ($matches == 0) {
            $this->interface->addWarningMessage('war_nomatches');
        }
        elseif ($this->request->getCommand() == BatcheditRequest::COMMAND_APPLY && !empty($this->request->getAppliedMatches())) {
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
}

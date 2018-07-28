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
require_once(DOKU_PLUGIN . 'action.php');
require_once(DOKU_PLUGIN . 'batchedit/admin.php');
require_once(DOKU_PLUGIN . 'batchedit/config.php');
require_once(DOKU_PLUGIN . 'batchedit/server.php');

class action_plugin_batchedit extends DokuWiki_Action_Plugin {

    const YEAR_IN_SECONDS = 31536000;

    /**
     * Register callbacks
     */
    public function register(Doku_Event_Handler $controller) {
        if ($this->isBatchEditAjax()) {
            $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'onAjaxCallUnknown');
        }

        if (!$this->isBatchEdit()) {
            return;
        }

        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'onBeforeHeadersSend');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'onBeforeMetaheaderOutput');
    }

    /**
     *
     */
    public function onAjaxCallUnknown($event, $param) {
        if ($event->data == 'batchedit') {
            $event->preventDefault();
            $event->stopPropagation();

            $server = new BatcheditServer($this);

            $server->handle();
        }
    }

    /**
     *
     */
    public function onBeforeHeadersSend($event, $param) {
        $admin = admin_plugin_batchedit::getInstance();

        if ($admin == NULL) {
            return;
        }

        setcookie(BatcheditConfig::COOKIE, $admin->getConfig()->serialize(), time() + self::YEAR_IN_SECONDS);
    }

    /**
     *
     */
    public function onBeforeMetaheaderOutput($event, $param) {
        $this->addTemplateHeaderInclude($event, 'interface.css');
        $this->addTemplateHeaderInclude($event, 'server.js');
        $this->addTemplateHeaderInclude($event, 'interface.js');
        $this->addTemplateHeaderInclude($event, 'js.cookie.js');
    }

    /**
     *
     */
    private function isBatchEditAjax() {
        return $_REQUEST['call'] == 'batchedit';
    }

    /**
     *
     */
    private function isBatchEdit() {
        return $_REQUEST['do'] == 'admin' && !empty($_REQUEST['page']) && $_REQUEST['page'] == 'batchedit';
    }

    /**
     *
     */
    private function addTemplateHeaderInclude($event, $fileName) {
        $type = '';
        $fileName = DOKU_BASE . 'lib/plugins/batchedit/' . $fileName;

        switch (pathinfo($fileName, PATHINFO_EXTENSION)) {
            case 'css':
                $type = 'link';
                $data = array('type' => 'text/css', 'rel' => 'stylesheet', 'href' => $fileName);
                break;

            case 'js':
                $type = 'script';
                $data = array('type' => 'text/javascript', 'charset' => 'utf-8', 'src' => $fileName, '_data' => '');
                break;
        }

        if ($type != '') {
            $event->data[$type][] = $data;
        }
    }
}

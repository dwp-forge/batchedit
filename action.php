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

class action_plugin_batchedit extends DokuWiki_Action_Plugin {

    /**
     * Register callbacks
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        if (($_REQUEST['do'] == 'admin') && !empty($_REQUEST['page']) && ($_REQUEST['page'] == 'batchedit')) {
            $this->addAdminIncludes($event);
        }
    }

    /**
     *
     */
    private function addAdminIncludes($event) {
        $this->addTemplateHeaderInclude($event, 'admin.css');
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
        }

        if ($type != '') {
            $event->data[$type][] = $data;
        }
    }
}

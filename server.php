<?php

/**
 * Plugin BatchEdit: AJAX server
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'batchedit/engine.php');

class BatcheditServer {

    const AJAX_COOKIE = '{7b4e584c-bf85-4f7b-953b-15e327df08ff}';

    private $plugin;
    /**
     *
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     *
     */
    public function handle() {
        try {
            $this->verifyAdminRights();

            switch ($_REQUEST['command']) {
                case 'cancel':
                    $this->handleCancel();
                    break;

                case 'progress':
                    $this->handleProgress();
                    break;
            }
        }
        catch (Exception $error) {
            $this->sendResponse(array('error' => 'server_error', 'message' => $error->getMessage()));
        }
    }

    /**
     *
     */
    private function verifyAdminRights() {
        global $conf;

        if (auth_quickaclcheck($conf['start']) < AUTH_ADMIN) {
            throw new Exception('Access denied');
        }
    }

    /**
     *
     */
    private function verifySession() {
        if (!isset($_REQUEST['session']) || preg_match('/^[0-9a-f]+$/', $_REQUEST['session']) != 1) {
            throw new Exception('Invalid session identifier');
        }
    }

    /**
     *
     */
    private function handleCancel() {
        $this->verifySession();

        BatcheditEngine::cancelOperation($_REQUEST['session']);
    }

    /**
     *
     */
    private function handleProgress() {
        $this->verifySession();

        $progress = new BatcheditProgress($_REQUEST['session']);

        list($operation, $progress) = $progress->get();

        if ($operation == BatcheditProgress::UNKNOWN) {
            throw new Exception('Progress unknown');
        }

        $this->sendResponse(array('operation' => $this->getProgressLabel($operation), 'progress' => $progress));
    }

    /**
     *
     */
    private function getProgressLabel($operation) {
        switch ($operation) {
            case BatcheditProgress::SEARCH:
                return $this->plugin->getLang('lbl_searching');

            case BatcheditProgress::APPLY:
                return $this->plugin->getLang('lbl_applying');
        }

        return '';
    }

    /**
     *
     */
    private function sendResponse($data) {
        header('Content-Type: application/json');
        print(self::AJAX_COOKIE . json_encode($data) . self::AJAX_COOKIE);
    }
}

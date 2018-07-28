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
    private function sendResponse($data) {
        header('Content-Type: application/json');
        print(self::AJAX_COOKIE . json_encode($data) . self::AJAX_COOKIE);
    }
}

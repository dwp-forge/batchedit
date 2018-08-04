<?php

/**
 * Plugin BatchEdit: Configuration
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditConfig {

    const COOKIE = 'BatchEditConfig';

    private $config;

    private static $defaults = array(
        'searchmode' => 'text',
        'matchcase' => FALSE,
        'multiline' => FALSE,
        'advregexp' => FALSE,
        'matchctx' => TRUE,
        'ctxchars' => 50,
        'ctxlines' => 3,
        'searchlimit' => TRUE,
        'searchmax' => 100,
        'keepmarks' => FALSE,
        'markpolicy' => 1,
        'checksummary' => TRUE
    );

    /**
     *
     */
    public function __construct() {
        $this->config = array();

        $this->loadCookie();
    }

    /**
     *
     */
    public function update($options) {
        $this->load($options);
    }

    /**
     *
     */
    public function getConfig() {
        return array_merge(self::$defaults, $this->config);
    }

    /**
     *
     */
    public function getConf($id) {
        if (array_key_exists($id, $this->config)) {
            return $this->config[$id];
        }

        if (array_key_exists($id, self::$defaults)) {
            return self::$defaults[$id];
        }

        return '';
    }

    /**
     *
     */
    public function serialize() {
        return json_encode($this->config);
    }

    /**
     *
     */
    private function loadCookie() {
        if (!array_key_exists(self::COOKIE, $_COOKIE)) {
            return;
        }

        $cookie = json_decode($_COOKIE[self::COOKIE], TRUE);

        if (!is_array($cookie)) {
            return;
        }

        $this->load($cookie);
    }

    /**
     * Sanitize user-provided data
     */
    private function load($options) {
        if (array_key_exists('searchmode', $options)) {
            $this->config['searchmode'] = $options['searchmode'] == 'regexp' ? 'regexp' : 'text';
        }

        $this->loadBoolean($options, 'matchcase');
        $this->loadBoolean($options, 'multiline');
        $this->loadBoolean($options, 'advregexp');
        $this->loadBoolean($options, 'matchctx');
        $this->loadInteger($options, 'ctxchars');
        $this->loadInteger($options, 'ctxlines');

        if ($this->getConf('ctxchars') == 0) {
            $this->config['matchctx'] = FALSE;
        }

        $this->loadBoolean($options, 'searchlimit');
        $this->loadInteger($options, 'searchmax');

        if ($this->getConf('searchmax') == 0) {
            $this->config['searchlimit'] = FALSE;
        }

        $this->loadBoolean($options, 'keepmarks');
        $this->loadInteger($options, 'markpolicy');
        $this->loadBoolean($options, 'checksummary');
        $this->loadInteger($options, 'searchheight');
        $this->loadInteger($options, 'replaceheight');
    }

    /**
     *
     */
    private function loadBoolean($options, $id) {
        if (array_key_exists($id, $options)) {
            $this->config[$id] = $options[$id] == TRUE;
        }
    }

    /**
     *
     */
    private function loadInteger($options, $id) {
        if (array_key_exists($id, $options) && $options[$id] !== '') {
            $this->config[$id] = max(intval($options[$id]), 0);
        }
    }
}

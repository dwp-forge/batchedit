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
        'checksummary' => TRUE
    );

    /**
     *
     */
    public function __construct() {
        $this->config = $this->load();
    }

    /**
     *
     */
    public function update($request) {
        $this->updateOption($request, 'searchmode');
        $this->updateOption($request, 'matchcase');
        $this->updateOption($request, 'multiline');
        $this->updateOption($request, 'checksummary');
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
    private function updateOption($request, $id) {
        $value = $request->getOption($id);

        if ($value !== NULL) {
            $this->config[$id] = $value;
        }
    }

    /**
     *
     */
    private function load() {
        if (!array_key_exists(self::COOKIE, $_COOKIE)) {
            return array();
        }

        $cookie = json_decode($_COOKIE[self::COOKIE], TRUE);

        if (!is_array($cookie)) {
            return array();
        }

        // Sanitize user-provided data
        $options = array();

        if (array_key_exists('searchmode', $cookie)) {
            $options['searchmode'] = $cookie['searchmode'] == 'regexp' ? 'regexp' : 'text';
        }

        if (array_key_exists('matchcase', $cookie)) {
            $options['matchcase'] = $cookie['matchcase'] == TRUE;
        }

        if (array_key_exists('multiline', $cookie)) {
            $options['multiline'] = $cookie['multiline'] == TRUE;
        }

        if (array_key_exists('checksummary', $cookie)) {
            $options['checksummary'] = $cookie['checksummary'] == TRUE;
        }

        if (array_key_exists('searchheight', $cookie)) {
            $options['searchheight'] = intval($cookie['searchheight']);
        }

        if (array_key_exists('replaceheight', $cookie)) {
            $options['replaceheight'] = intval($cookie['replaceheight']);
        }

        return $options;
    }
}

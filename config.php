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
        $this->updateOption($request, 'advregexp');
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

        $this->loadBoolean($cookie, 'matchcase', $options);
        $this->loadBoolean($cookie, 'multiline', $options);
        $this->loadBoolean($cookie, 'advregexp', $options);
        $this->loadBoolean($cookie, 'checksummary', $options);
        $this->loadInteger($cookie, 'searchheight', $options);
        $this->loadInteger($cookie, 'replaceheight', $options);

        return $options;
    }

    /**
     *
     */
    private function loadBoolean($cookie, $id, &$options) {
        if (array_key_exists($id, $cookie)) {
            $options[$id] = $cookie[$id] == TRUE;
        }
    }

    /**
     *
     */
    private function loadInteger($cookie, $id, &$options) {
        if (array_key_exists($id, $cookie)) {
            $options[$id] = intval($cookie[$id]);
        }
    }
}

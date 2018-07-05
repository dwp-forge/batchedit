<?php

/**
 * Plugin BatchEdit: Configuration
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditConfig {

    private $fileName;
    private $config;

    private static $defaults = array(
        'searchmode' => 'text',
        'matchcase' => FALSE
    );

    /**
     *
     */
    public function __construct() {
        $this->fileName = DOKU_CONF . 'batchedit.local.json';
        $this->config = $this->load();
    }

    /**
     *
     */
    public function update($request) {
        $this->updateOption($request, 'searchmode');
        $this->updateOption($request, 'matchcase');
        $this->save();
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
        if (!file_exists($this->fileName)) {
            return array();
        }

        return json_decode(io_readFile($this->fileName, FALSE), TRUE);
    }

    /**
     *
     */
    private function save() {
        io_saveFile($this->fileName, json_encode($this->config, JSON_PRETTY_PRINT));
    }
}

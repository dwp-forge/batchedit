<?php
/**
 * Plugin BatchEdit
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    var $error;
    var $command;
    var $regexp;
    var $pageIndex;
    var $match;

    function admin_plugin_batchedit() {
        $this->error = '';
        $this->command = 'hello';
        $this->regexp = '';
        $this->replacement = '';
        $this->pageIndex = array();
        $this->match = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2008-09-13',
            'name'   => 'BatchEdit',
            'desc'   => 'Edit wiki pages with regexp replacement.',
            'url'    => 'http://www.dokuwiki.org/plugin:adminskeleton',
        );
    }

    /**
     * Handle user request
     */
    function handle() {

        if (!isset($_REQUEST['cmd'])) {
            // First time - nothing to do
            return;
        }

        try
        {
            if (!is_array($_REQUEST['cmd'])) {
                throw new Exception('err_invcmd');
            }

            $this->command = key($_REQUEST['cmd']);

            switch ($this->command) {
                case 'preview':
                    $this->_preview();
                    break;

                case 'apply':
                    $this->_preview();
                    break;

                default:
                    throw new Exception('err_invcmd');
                    break;
            }
        }
        catch ( Exception $error )
        {
            $this->error = $this->getLang($error->getMessage());
        }
    }

    /**
     * Output appropriate html
     */
    function html() {
        global $ID;

        ptln('<!-- batchedit -->');
        ptln('<div id="batchedit">');

        if ($this->error != '') {
            $this->_printError();
        }

        ptln('<form action="' . wl($ID) . '" method="post">');

        if ($this->error == '') {
            switch ($this->command) {
                case 'preview':
                    $this->_printMatches();
                    break;

                case 'apply':
                    $this->_printMatches();
                    break;
            }
        }

        $this->_printMainForm();

        ptln('</form>');
        ptln('</div>');
        ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    function _preview() {
        $this->_initRegexp();
        $this->_loadPageIndex();
        $this->_findMatches();
    }

    /**
     *
     */
    function _initRegexp() {
        if (!isset($_REQUEST['regexp'])) {
            throw new Exception('err_noregexp');
        }

        $this->regexp = trim($_REQUEST['regexp']);

        if ($this->regexp == '') {
            throw new Exception('err_noregexp');
        }

        if (preg_match('/^([\/|!#-]).+?\1[imsxeADSUXJu]?$/', $this->regexp) != 1) {
            throw new Exception('err_invregexp');
        }

        if (!isset($_REQUEST['replace'])) {
            throw new Exception('err_noreplace');
        }

        $this->replacement = $_REQUEST['replace'];
    }

    /**
     *
     */
    function _loadPageIndex() {
        global $conf;

        if (@file_exists($conf['indexdir'] . '/page.idx')) {
            require_once(DOKU_INC . 'inc/indexer.php');

            $this->pageIndex = idx_getIndex('page', '');

            if (count($this->pageIndex) == 0) {
                throw new Exception('err_emptyidx');
            }
        }
        else {
            throw new Exception('err_idxaccess');
        }
    }

    /**
     *
     */
    function _findMatches() {
        foreach ($this->pageIndex as $p) {
            $page = trim($p);
            $this->_findPageMatches($page);
        }
    }

    /**
     *
     */
    function _findPageMatches($page) {
        $text = io_readFile(wikiFN($page));
        $count = preg_match_all($this->regexp, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new Exception('err_pregfailed');
        }

        for ($i = 0; $i < $count; $i++) {
            $info['original'] = $match[$i][0][0];
            $info['replaced'] = preg_replace($this->regexp, $this->replacement, $info['original']);
            $info['offest'] = $match[$i][0][1];
            $info['before'] = $this->_getBeforeContext($text, $match[$i]);
            $info['after'] = $this->_getAfterContext($text, $match[$i]);

            $this->match[$page][$i] = $info;
        }
    }

    /**
     *
     */
    function _getBeforeContext($text, $match) {
        $length = 50;
        $offset = $match[0][1] - $length;

        if ($offset < 0) {
            $length += $offset;
            $offset = 0;
        }

        $text = substr($text, $offset, $length);
        $count = preg_match_all('/\n/', $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > 3) {
            $text = substr($text, $match[$count - 4][0][1] + 1);
        }

        return $text;
    }

    /**
     *
     */
    function _getAfterContext($text, $match) {
        $offset = $match[0][1] + strlen($match[0][0]);
        $text = substr($text, $offset, 50);
        $count = preg_match_all('/\n/', $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > 3) {
            $text = substr($text, 0, $match[3][0][1]);
        }

        return $text;
    }

    /**
     *
     */
    function _printPageIndex() {
        foreach ($this->pageIndex as $p) {
            ptln('  ' . $p . '<br />');
        }
    }

    /**
     *
     */
    function _printMatches() {
        foreach ($this->match as $page => $match) {
            foreach ($match as $info) {
                $original = $this->_prepareText($info['original'], TRUE);
                $replaced = $this->_prepareText($info['replaced'], TRUE);
                $before = $this->_prepareText($info['before']);
                $after = $this->_prepareText($info['after']);
                $id = $page . '#' . $info['offest'];

                ptln('<div class="file">');
                ptln('<input type="checkbox" id="' . $id . '" name="apply[' . $id . ']" value="on" />');
                ptln('<label for="' . $id . '">' . $id . '</label>');
                ptln('<table><tr>');
                ptln('<td class="text">');
                ptln($before . $original . $after);
                ptln('</td>');
                ptln('<td style="width: 2%; font-size: 200%">&gt;</td>');
                ptln('<td class="text">');
                ptln($before . $replaced . $after);
                ptln('</td>');
                ptln('</tr></table>');
                ptln('</div>');
            }
        }
    }

    /**
     *
     */
    function _prepareText($text, $highlight = FALSE) {
        $html = htmlspecialchars($text);
        $html = str_replace( "\n", '<br />', $html);

        if ($highlight) {
            $html = '<strong class="search_hit">' . $html . '</strong>';
        }

        return $html;
    }

    /**
     *
     */
    function _printError() {
        ptln('<div class="error">');
        ptln('<b>Error:</b> ' . $this->error);
        ptln('</div>');
    }

    /**
     *
     */
    function _printMainForm() {

        ptln('<div class="mainform">');

        // Output hidden values to ensure dokuwiki will return back to this plugin
        ptln('  <input type="hidden" name="do"   value="admin" />');
        ptln('  <input type="hidden" name="page" value="' . $this->getPluginName() . '" />');

        ptln('  <table>');
        $this->_printFormEdit('lbl_ns', 'namespace');
        $this->_printFormEdit('lbl_regexp', 'regexp');
        $this->_printFormEdit('lbl_replace', 'replace');
        $this->_printFormEdit('lbl_comment', 'comment');
        ptln('  </table>');

        ptln('  <input type="submit" class="button" name="cmd[preview]"  value="' . $this->getLang('btn_preview') . '" />');
        ptln('  <input type="submit" class="button" name="cmd[apply]"  value="' . $this->getLang('btn_apply') . '" />');

        ptln('</div>');
    }

    /**
     *
     */
    function _printFormEdit($title, $name) {
        $value = '';

        if (isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];
        }

        ptln( '   <tr><td style="width: 5%; padding-right: 1em;"><nobr><b>' . $this->getLang($title) . ':</b></nobr></td><td>');
        ptln( '   <input type="text" class="edit" name="' . $name . '" value="' . $value . '" />');
        ptln( '   </td></tr>');
    }
}

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

class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    private $error;
    private $warning;
    private $command;
    private $namespace;
    private $regexp;
    private $replacement;
    private $summary;
    private $minorEdit;
    private $pageIndex;
    private $match;
    private $matches;
    private $edits;
    private $indent;
    private $svgCache;

    public function __construct() {
        $this->error = '';
        $this->warning = array();
        $this->command = 'hello';
        $this->checkedMatches = '';
        $this->namespace = '';
        $this->regexp = '';
        $this->replacement = '';
        $this->summary = '';
        $this->minorEdit = FALSE;
        $this->pageIndex = array();
        $this->match = array();
        $this->matches = 0;
        $this->edits = 0;
        $this->indent = 0;
        $this->svgCache = array();
    }

    /**
     *
     */
    public function getLang($id) {
        $string = parent::getLang($id);

        if (func_num_args() > 1) {
            $search = array();
            $replace = array();

            for ($i = 1; $i < func_num_args(); $i++) {
                $search[$i - 1] = '{' . $i . '}';
                $replace[$i - 1] = func_get_arg($i);
            }

            $string = str_replace($search, $replace, $string);
        }

        return $string;
    }

    /**
     *
     */
    public function getLangPlural($id, $quantity) {
        if ($quantity == 1) {
            return $this->getLang($id . '#one', $quantity);
        }

        return $this->getLang($id . '#many', $quantity);
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
            $this->parseRequest();

            switch ($this->command) {
                case 'preview':
                    $this->preview();
                    break;

                case 'apply':
                    $this->apply();
                    break;
            }
        }
        catch (Exception $error) {
            $this->error = $this->getLang($error->getMessage());
        }
    }

    /**
     * Output appropriate html
     */
    public function html() {
        global $ID;

        ptln('<!-- batchedit -->');
        ptln('<div id="batchedit">');

        $this->printMessages();

        ptln('<form id="replace-form" action="' . wl($ID) . '" method="post">');

        if ($this->error == '' && !empty($this->match)) {
            switch ($this->command) {
                case 'preview':
                case 'apply':
                    $this->printMatches();
                    break;
            }
        }

        $this->printMainForm();

        ptln('</form>');
        ptln('</div>');
        ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    private function parseRequest() {
        $this->command = $this->getCommand();
        $this->checkedMatches = $this->getCheckedMatches();
        $this->namespace = $this->getNamespace();
        $this->regexp = $this->getRegexp();
        $this->replacement = $this->getReplacement();
        $this->summary = $this->getSummary();
        $this->minorEdit = isset($_REQUEST['minor']);
    }

    /**
     *
     */
    private function getCommand() {
        if (!is_array($_REQUEST['cmd'])) {
            throw new Exception('err_invreq');
        }

        $command = key($_REQUEST['cmd']);

        if (($command != 'preview') && ($command != 'apply')) {
            throw new Exception('err_invreq');
        }

        return $command;
    }

    /**
     *
     */
    private function getCheckedMatches() {
        if (!isset($_REQUEST['checkedMatches'])) {
            return '';
        }

        if (!is_string($_REQUEST['checkedMatches'])) {
            throw new Exception('err_invreq');
        }

        return $_REQUEST['checkedMatches'];
    }

    /**
     *
     */
    private function getNamespace() {
        if (!isset($_REQUEST['namespace'])) {
            throw new Exception('err_invreq');
        }

        $namespace = trim($_REQUEST['namespace']);

        if ($namespace != '') {
            global $ID;

            $namespace = resolve_id(getNS($ID), $namespace . ':');

            if ($namespace != '') {
                $namespace .= ':';
            }
        }

        return $namespace;
    }

    /**
     *
     */
    private function getRegexp() {
        if (!isset($_REQUEST['regexp'])) {
            throw new Exception('err_invreq');
        }

        $regexp = trim($_REQUEST['regexp']);

        if ($regexp == '') {
            throw new Exception('err_noregexp');
        }

        if (preg_match('/^([^\w\\\\]|_).+?\1[imsxeADSUXJu]*$/', $regexp) != 1) {
            throw new Exception('err_invregexp');
        }

        return $regexp;
    }

    /**
     *
     */
    private function getReplacement() {
        if (!isset($_REQUEST['replace'])) {
            throw new Exception('err_invreq');
        }

        $unescape = function($matches) {
            if (strlen($matches[1]) % 2) {
                $unescaped = array('n' => "\n", 'r' => "\r", 't' => "\t");

                return substr($matches[1], 1) . $unescaped[$matches[2]];
            }
            else {
                return $matches[0];
            }
        };

        return preg_replace_callback('/(\\\\+)([nrt])/', $unescape, $_REQUEST['replace']);
    }

    /**
     *
     */
    private function getSummary() {
        if (!isset($_REQUEST['summary'])) {
            throw new Exception('err_invreq');
        }

        return $_REQUEST['summary'];
    }

    /**
     *
     */
    private function preview() {
        $this->loadPageIndex();
        $this->findMatches();
        $this->markRequested(explode(',', $this->checkedMatches));
    }

    /**
     *
     */
    private function loadPageIndex() {
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
    private function findMatches() {
        if ($this->namespace != '') {
            $pattern = '/^' . $this->namespace . '/';
        }
        else {
            $pattern = '';
        }

        foreach ($this->pageIndex as $p) {
            $page = trim($p);

            if (($pattern == '') || (preg_match($pattern, $page) == 1)) {
                $this->findPageMatches($page);
            }
        }

        if (empty($this->match)) {
            $this->warning[] = $this->getLang('war_nomatches');
        }
    }

    /**
     *
     */
    private function findPageMatches($page) {
        $text = rawWiki($page);
        $count = @preg_match_all($this->regexp, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new Exception('err_pregfailed');
        }

        $this->matches += $count;

        for ($i = 0; $i < $count; $i++) {
            $info['original'] = $match[$i][0][0];
            $info['replaced'] = preg_replace($this->regexp, $this->replacement, $info['original']);
            $info['offest'] = $match[$i][0][1];
            $info['before'] = $this->getBeforeContext($text, $match[$i]);
            $info['after'] = $this->getAfterContext($text, $match[$i]);
            $info['apply'] = FALSE;

            $this->match[$page][$i] = $info;
        }
    }

    /**
     *
     */
    private function getBeforeContext($text, $match) {
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
    private function getAfterContext($text, $match) {
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
    private function apply() {
        $this->loadPageIndex();
        $this->findMatches();

        $this->markRequested(explode(',', $this->checkedMatches));
        $this->applyMatches();
    }

    /**
     *
     */
    private function markRequested($request) {
        foreach ($request as $r) {
            list($page, $offset) = explode('#', $r);

            if (array_key_exists($page, $this->match)) {
                $count = count($this->match[$page]);

                for ($i = 0; $i < $count; $i++) {
                    if ($this->match[$page][$i]['offest'] == $offset) {
                        $this->match[$page][$i]['apply'] = TRUE;
                        break;
                    }
                }
            }
        }
    }

    /**
     *
     */
    private function applyMatches() {
        set_time_limit(120);
        foreach (array_keys($this->match) as $page) {
            if ($this->requiresChanges($page)) {
                if ($this->isEditAllowed($page)) {
                    $this->editPage($page);
                }
                else {
                    $this->unmarkDenied($page);
                }
            }
        }

        foreach ($this->match as $page => $match) {
            foreach ($match as $info) {
                $this->edits += $info['apply'] ? 1 : 0;
            }
        }
    }

    /**
     *
     */
    private function requiresChanges($page) {
        $result = FALSE;

        foreach ($this->match[$page] as $info) {
            if ($info['apply']) {
                $result = TRUE;
                break;
            }
        }

        return $result;
    }

    /**
     * applicable here means "not yet applied", so that we should still be able to check/uncheck them
     */
    private function hasApplicableMatches($page) {
        $result = FALSE;

        foreach ($this->match[$page] as $info) {
            if (!($this->command == 'apply' && $info['apply'])) {
                $result = TRUE;
                break;
            }
        }

        return $result;
    }

    /**
     *
     */
    private function hasCheckedAll($page) {
        if (empty($this->match[$page])) {
            return FALSE;
        }

        $result = TRUE;

        foreach ($this->match[$page] as $info) {
            if (!$info['apply']) {
                $result = FALSE;
                break;
            }
        }

        return $result;
    }

    /**
     *
     */
    private function isEditAllowed($page) {
        $allowed = TRUE;

        if (auth_quickaclcheck($page) < AUTH_EDIT) {
            $this->warning[] = $this->getLang('war_norights', $page);
            $allowed = FALSE;
        }

        if ($allowed) {
            $lockedBy = checklock($page);
            if ($lockedBy != FALSE) {
                $this->warning[] = $this->getLang('war_pagelock', $page, $lockedBy);
                $allowed = FALSE;
            }
        }

        return $allowed;
    }

    /**
     *
     */
    private function editPage($page) {
        set_time_limit(120);
        lock($page);

        $text = rawWiki($page);
        $offset = 0;

        foreach ($this->match[$page] as $info) {
            if ($info['apply']) {
                $originalLength = strlen($info['original']);
                $before = substr($text, 0, $info['offest'] + $offset);
                $after = substr($text, $info['offest'] + $offset + $originalLength);
                $text = $before . $info['replaced'] . $after;
                $offset += strlen($info['replaced']) - $originalLength;
            }
        }

        saveWikiText($page, $text, $this->summary, $this->minorEdit);
        unlock($page);
    }

    /**
     *
     */
    private function unmarkDenied($page) {
        $count = count($this->match[$page]);

        for ($i = 0; $i < $count; $i++) {
            $this->match[$page][$i]['apply'] = FALSE;
        }
    }

    /**
     *
     */
    private function printMatches() {
        $this->printTotalStats();

        foreach ($this->match as $page => $match) {
            $this->ptln('<div class="file">', +2);
            $this->printPageStats($page, $match);
            $this->printPageActions($page);
            $this->printPageMatches($page, $match);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printTotalStats() {
        $matches = $this->getLangPlural('sts_matches', $this->matches);
        $pages = $this->getLangPlural('sts_pages', count($this->match));

        switch ($this->command) {
            case 'preview':
                $stats = $this->getLang('sts_preview', $matches, $pages);
                break;

            case 'apply':
                $edits = $this->getLangPlural('sts_edits', $this->edits);
                $stats = $this->getLang('sts_apply', $matches, $pages, $edits);
                break;
        }

        $this->ptln('<div id="totalstats"><div>', +2);

        if ($this->edits < $this->matches) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyall') . '">', +2);
            $this->ptln('<input type="checkbox" id="applyall" />');
            $this->ptln('<label for="applyall">' . $stats . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln($stats);
        }

        $this->ptln('</div></div>', -2);
    }

    /**
     *
     */
    private function printPageStats($page, $match) {
        $stats = $this->getLang('sts_page', $page, $this->getLangPlural('sts_matches', count($match)));

        $this->ptln('<div class="stats">', +2);

        if ($this->hasApplicableMatches($page)) {
            $checkedAll = $this->hasCheckedAll($page);
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyfile') . '">', +2);
            $this->ptln('<input class="check-full-page" type="checkbox" ' . ($checkedAll ? 'checked ' : '') . 'id="' . $page . '" />');
            $this->ptln('<label for="' . $page . '">' . $stats . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln($stats);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printPageActions($page) {
        $link = wl($page);

        $this->ptln('<div class="actions">', +2);
        $this->printAction($link, 'ttl_view', 'file-document');
        $this->printAction($link . '&do=edit', 'ttl_edit', 'pencil');
        $this->printAction('#mainform', 'ttl_mainform', 'arrow-down');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printAction($href, $titleId, $iconId) {
        $action = '<a class="action" href="' . $href . '" title="' . $this->getLang($titleId) . '">';
        $action .= $this->getSvg($iconId);
        $action .= '</a>';

        $this->ptln($action);
    }

    /**
     *
     */
    private function printPageMatches($page, $match) {
        foreach ($match as $info) {
            $this->ptln('<div class="match">', +2);
            $this->printMatchHeader($page, $info);
            $this->printMatchTable($info);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printMatchHeader($page, $info) {
        $id = $page . '#' . $info['offest'];
        if ($info['apply'] && $this->command == 'apply') {
            $this->ptln('<div class="match-id">' . $id . '</div>');
        }
        else {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applymatch') . '">', +2);
            $this->ptln('<input class="check-single-match" type="checkbox" ' . ($info['apply'] ? 'checked ' : '') . 'id="' . $id . '" />');
            $this->ptln('<label class="match-id" for="' . $id . '">' . $id . '</label>');
            $this->ptln('</span>', -2);
        }
    }

    /**
     *
     */
    private function printMatchTable($info) {
        $original = $this->prepareText($info['original'], 'search_hit' . ($info['apply'] ? ' replaced' : ''));
        $replaced = $this->prepareText($info['replaced'], 'search_hit' . ($info['apply'] ? ' applied' : ''));
        $before = $this->prepareText($info['before']);
        $after = $this->prepareText($info['after']);

        $this->ptln('<table><tr>', +2);
        $this->ptln('<td class="text">' . $before . $original . $after . '</td>');
        $this->ptln('<td class="arrow">' . $this->getSvg('slide-arrow-right') . '</td>');
        $this->ptln('<td class="text">' . $before . $replaced . $after . '</td>');
        $this->ptln('</tr></table>', -2);
    }

    /**
     * Prepare wiki text to be displayed as html
     */
    private function prepareText($text, $highlight = '') {
        $html = htmlspecialchars($text);
        $html = str_replace("\n", '<br />', $html);

        if ($highlight != '') {
            $html = '<span class="' . $highlight . '">' . $html . '</span>';
        }

        return $html;
    }

    /**
     *
     */
    private function printMessages() {
        if ((count($this->warning) > 0) || ($this->error != '')) {
            $this->ptln('<div id="messages">', +2);

            $this->printWarnings();

            if ($this->error != '') {
                $this->printError();
            }

            $this->ptln('</div>', -2);
            ptln('');
        }
    }

    /**
     *
     */
    private function printWarnings() {
        foreach($this->warning as $w) {
            $this->ptln('<div class="notify">', +2);
            $this->ptln('<b>Warning:</b> ' . $w);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printError() {
        $this->ptln('<div class="error">', +2);
        $this->ptln('<b>Error:</b> ' . $this->error);
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printMainForm() {

        $this->ptln('<div id="mainform">', +2);

        // Output hidden values to ensure dokuwiki will return back to this plugin
        $this->ptln('<input type="hidden" name="do"   value="admin" />');
        $this->ptln('<input type="hidden" name="page" value="' . $this->getPluginName() . '" />');

        $this->ptln('<table>', +2);
        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_regexp', 'regexp');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');
        $this->ptln('</table>', -2);


        // Value for this hidden input is set before submit through jQuery, containing
        // comma-separated list of all checked checkbox ids for single matches, so that
        // they can be checked again on the result page as applicable.
        // Consolidating these inputs into a single string variable avoids problems for
        // huge replacement sets exceeding `max_input_vars` in `php.ini`.
        $this->ptln('<input type="hidden" name="checkedMatches" value="">');

        $this->ptln('<input type="submit" class="button" name="cmd[preview]"  value="' . $this->getLang('btn_preview') . '" />');
        $this->ptln('<input type="submit" class="button" name="cmd[apply]"  value="' . $this->getLang('btn_apply') . '" />');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printFormEdit($title, $name) {
        $value = '';

        if (isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];
        }

        $this->ptln('<tr>', +2);
        $this->ptln('<td class="title"><nobr><b>' . $this->getLang($title) . ':</b></nobr></td>');
        $this->ptln('<td class="edit"><input type="text" class="edit" name="' . $name . '" value="' . $value . '" /></td>');

        switch ($name) {
            case 'summary':
                $this->ptln('<td style="padding-left: 2em">', +2);
                $this->printCheckBox('lbl_minor', 'minor');
                $this->ptln('</td>', -2);
                break;

            case 'regexp':
                $this->ptln('<td style="padding-left: 2em">' . $this->getLang('inf_regexp') . '</td>');
                break;

            case 'replace':
                $this->ptln('<td style="padding-left: 2em">' . $this->getLang('inf_replace') . '</td>');
                break;

            default:
                $this->ptln('<td></td>');
                break;
        }

        $this->ptln('</tr>', -2);
    }

    /**
     *
     */
    private function printCheckBox($title, $name) {
        $html = '<input type="checkbox" id="' . $name . '" name="' . $name . '" value="on"';

        if (isset($_REQUEST[$name])) {
            $html .= ' checked="checked"';
        }

        $this->ptln($html . ' />');
        $this->ptln('<label for="' . $name . '">' . $this->getLang($title) . '</label>');
    }

    /**
     *
     */
    private function ptln($string, $indentDelta = 0) {
        if ($indentDelta < 0) {
            $this->indent += $indentDelta;
        }

        ptln($string, $this->indent);

        if ($indentDelta > 0) {
            $this->indent += $indentDelta;
        }
    }

    /**
     *
     */
    private function getSvg($id) {
        if (!array_key_exists($id, $this->svgCache)) {
            $this->svgCache[$id] = file_get_contents(DOKU_PLUGIN . 'batchedit/images/' . $id . '.svg');
        }

        return $this->svgCache[$id];
    }
}

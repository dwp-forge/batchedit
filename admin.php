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
require_once(DOKU_PLUGIN . 'batchedit/engine.php');
require_once(DOKU_PLUGIN . 'batchedit/request.php');

class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    private $error;
    private $warning;
    private $request;
    private $pageIndex;
    private $match;
    private $matches;
    private $edits;
    private $indent;
    private $svgCache;

    public function __construct() {
        $this->error = '';
        $this->warning = array();
        $this->request = NULL;
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
            $this->request = new BatcheditRequest();

            switch ($this->request->getCommand()) {
                case BatcheditRequest::COMMAND_PREVIEW:
                    $this->preview();
                    break;

                case BatcheditRequest::COMMAND_APPLY:
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

        ptln('<form action="' . wl($ID) . '" method="post">');

        if ($this->error == '' && !empty($this->request) && !empty($this->match)) {
            $this->printMatches();
        }

        $this->printMainForm();

        ptln('</form>');
        ptln('</div>');
        ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    private function preview() {
        $this->loadPageIndex();
        $this->findMatches();
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
        if ($this->request->getNamespace() != '') {
            $pattern = '/^' . $this->request->getNamespace() . '/';
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
        $count = @preg_match_all($this->request->getRegexp(), $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new Exception('err_pregfailed');
        }

        $this->matches += $count;

        for ($i = 0; $i < $count; $i++) {
            $this->match[$page][$match[$i][0][1]] = new BatcheditMatch($text, $match[$i][0][1], $match[$i][0][0],
                    $this->request->getRegexp(), $this->request->getReplacement());
        }
    }

    /**
     *
     */
    private function apply() {
        $this->loadPageIndex();
        $this->findMatches();

        if (!empty($this->request->getAppliedMatches())) {
            $this->markRequested($this->request->getAppliedMatches());
            $this->applyMatches();
        }
    }

    /**
     *
     */
    private function markRequested($request) {
        foreach ($request as $matchId) {
            list($page, $offset) = explode('#', $matchId);

            if (array_key_exists($page, $this->match)) {
                if (array_key_exists($offset, $this->match[$page])) {
                    $this->match[$page][$offset]->mark();
                }
            }
        }
    }

    /**
     *
     */
    private function applyMatches() {
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
            foreach ($match as $m) {
                $this->edits += $m->isMarked() ? 1 : 0;
            }
        }
    }

    /**
     *
     */
    private function requiresChanges($page) {
        $result = FALSE;

        foreach ($this->match[$page] as $match) {
            if ($match->isMarked()) {
                $result = TRUE;
                break;
            }
        }

        return $result;
    }

    /**
     *
     */
    private function hasApplicableMatches($page) {
        $result = FALSE;

        foreach ($this->match[$page] as $match) {
            if (!$match->isMarked()) {
                $result = TRUE;
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
        lock($page);

        $text = rawWiki($page);
        $originalLength = strlen($text);

        foreach ($this->match[$page] as $match) {
            if ($match->isMarked()) {
                $text = $match->apply($text, strlen($text) - $originalLength);
            }
        }

        saveWikiText($page, $text, $this->request->getSummary(), $this->request->getMinorEdit());
        unlock($page);
    }

    /**
     *
     */
    private function unmarkDenied($page) {
        foreach ($this->match[$page] as $match) {
            $match->mark(FALSE);
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

        switch ($this->request->getCommand()) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $stats = $this->getLang('sts_preview', $matches, $pages);
                break;

            case BatcheditRequest::COMMAND_APPLY:
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
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyfile') . '">', +2);
            $this->ptln('<input type="checkbox" id="' . $page . '" />');
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
        foreach ($match as $m) {
            $this->ptln('<div class="match">', +2);
            $this->printMatchHeader($page, $m);
            $this->printMatchTable($m);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printMatchHeader($page, $match) {
        $id = $page . '#' . $match->getPageOffset();

        if (!$match->isMarked()) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applymatch') . '">', +2);
            $this->ptln('<input type="checkbox" id="' . $id . '" name="apply[' . $id . ']" value="on" />');
            $this->ptln('<label class="match-id" for="' . $id . '">' . $id . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln('<div class="match-id">' . $id . '</div>');
        }
    }

    /**
     *
     */
    private function printMatchTable($match) {
        $original = $this->prepareText($match->getOriginalText(), 'search_hit' . ($match->isMarked() ? ' replaced' : ''));
        $replaced = $this->prepareText($match->getReplacedText(), 'search_hit' . ($match->isMarked() ? ' applied' : ''));
        $before = $this->prepareText($match->getContextBefore());
        $after = $this->prepareText($match->getContextAfter());

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

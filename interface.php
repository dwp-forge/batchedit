<?php

/**
 * Plugin BatchEdit: User interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditMessage {
    const ERROR = 1;
    const WARNING = 2;

    public $type;
    public $text;

    /**
     *
     */
    public function __construct($type, $text) {
        $this->type = $type;
        $this->text = $text;
    }

    /**
     *
     */
    public function getClass() {
        switch ($this->type) {
            case self::ERROR:
                return 'error';
            case self::WARNING:
                return 'notify';
        }

        return '';
    }

    /**
     *
     */
    public function getFormatId() {
        switch ($this->type) {
            case self::ERROR:
                return 'msg_error';
            case self::WARNING:
                return 'msg_warning';
        }

        return '';
    }
}

class BatcheditInterface {

    private $plugin;
    private $messages;
    private $indent;
    private $svgCache;

    /**
     *
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->messages = array();
        $this->indent = 0;
        $this->svgCache = array();
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function addErrorMessage($id) {
        $this->addMessage(BatcheditMessage::ERROR, func_get_args());
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function addWarningMessage($id) {
        $this->addMessage(BatcheditMessage::WARNING, func_get_args());
    }

    /**
     *
     */
    public function printBeginning() {
        global $ID;

        $this->ptln('<!-- batchedit -->');
        $this->ptln('<div id="batchedit">');

        $this->printJavascriptLang();

        $this->ptln('<form action="' . wl($ID) . '" method="post">');
    }

    /**
     *
     */
    public function printEnding() {
        $this->ptln('</form>');
        $this->ptln('</div>');
        $this->ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    public function printMessages() {
        if (empty($this->messages)) {
            return;
        }

        $this->ptln('<div id="messages">', +2);

        foreach($this->messages as $message) {
            $this->ptln('<div class="' . $message->getClass() . '">', +2);
            $this->ptln($this->getLang($message->getFormatId(), $message->text));
            $this->ptln('</div>', -2);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    public function printTotalStats($command, $matchCount, $pageCount, $editCount) {
        $matches = $this->getLangPlural('sts_matches', $matchCount);
        $pages = $this->getLangPlural('sts_pages', $pageCount);

        switch ($command) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $stats = $this->getLang('sts_preview', $matches, $pages);
                break;

            case BatcheditRequest::COMMAND_APPLY:
                $edits = $this->getLangPlural('sts_edits', $editCount);
                $stats = $this->getLang('sts_apply', $matches, $pages, $edits);
                break;
        }

        $this->ptln('<div id="totalstats"><div>', +2);

        if ($editCount < $matchCount) {
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
    public function printMatches($pages) {
        foreach ($pages as $page) {
            $this->ptln('<div class="file">', +2);
            $this->printPageStats($page);
            $this->printPageActions($page->getId());
            $this->printPageMatches($page);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    public function printMainForm() {
        $this->ptln('<div id="mainform">', +2);

        // Output hidden values to ensure dokuwiki will return back to this plugin
        $this->ptln('<input type="hidden" name="do"   value="admin" />');
        $this->ptln('<input type="hidden" name="page" value="' . $this->plugin->getPluginName() . '" />');

        $this->ptln('<table>', +2);
        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_search', 'search');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');
        $this->ptln('</table>', -2);

        $this->printOptions();

        $this->ptln('<input type="submit" class="button" name="cmd[preview]"  value="' . $this->getLang('btn_preview') . '" />');
        $this->ptln('<input type="submit" class="button" name="cmd[apply]"  value="' . $this->getLang('btn_apply') . '" />');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function addMessage($type, $arguments) {
        $this->messages[] = new BatcheditMessage($type, call_user_func_array(array($this, 'getLang'), $arguments));
    }

    /**
     *
     */
    private function printJavascriptLang() {
        $this->ptln('<script type="text/javascript">');

        $langIds = array('hnt_textsearch', 'hnt_textreplace', 'hnt_regexpsearch', 'hnt_regexpreplace');
        $lang = array();

        foreach ($langIds as $id) {
            $lang[$id] = $this->getLang($id);
        }

        $this->ptln('var batcheditLang = ' . json_encode($lang) . ';');
        $this->ptln('</script>');
    }

    /**
     *
     */
    private function printPageStats($page) {
        $stats = $this->getLang('sts_page', $page->getId(), $this->getLangPlural('sts_matches', count($page->getMatches())));

        $this->ptln('<div class="stats">', +2);

        if ($page->hasUnmarkedMatches()) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyfile') . '">', +2);
            $this->ptln('<input type="checkbox" id="' . $page->getId() . '" />');
            $this->ptln('<label for="' . $page->getId() . '">' . $stats . '</label>');
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
    private function printPageActions($pageId) {
        $link = wl($pageId);

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
    private function printPageMatches($page) {
        foreach ($page->getMatches() as $match) {
            $this->ptln('<div class="match">', +2);
            $this->printMatchHeader($page->getId(), $match);
            $this->printMatchTable($match);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printMatchHeader($pageId, $match) {
        $id = $pageId . '#' . $match->getPageOffset();

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
    private function printFormEdit($title, $name) {
        $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';

        $this->ptln('<tr>', +2);

        $this->ptln('<td class="title">' . $this->getLang($title) . '</td>');

        $this->ptln('<td class="edit">', +2);
        $this->ptln('<input type="text" class="edit" name="' . $name . '" value="' . $value . '" />');

        switch ($name) {
            case 'summary':
                $this->printCheckBox('minor', 'lbl_minor');
                break;
        }

        $this->ptln('</td>', -2);

        $this->ptln('</tr>', -2);
    }

    /**
     *
     */
    private function printOptions() {
        $this->ptln('<div id="options"><div>', +2);

        $this->ptln('<div class="radiogroup">', +2);
        $this->ptln('<div>' . $this->getLang('lbl_searchmode') . '</div>');
        $this->printRadioButton('searchmode', 'text', 'lbl_searchtext');
        $this->printRadioButton('searchmode', 'regexp', 'lbl_searchregexp');
        $this->ptln('</div>', -2);

        $this->ptln('</div></div>', -2);
    }

    /**
     *
     */
    private function printCheckBox($name, $label) {
        $html = '<input type="checkbox" id="' . $name . '" name="' . $name . '" value="on"';

        if (isset($_REQUEST[$name])) {
            $html .= ' checked="checked"';
        }

        $this->ptln($html . ' />');
        $this->ptln('<label for="' . $name . '">' . $this->getLang($label) . '</label>');
    }

    /**
     *
     */
    private function printRadioButton($group, $name, $label) {
        $id = $group . $name;
        $html = '<input type="radio" id="' . $id . '" name="' . $group . '" value="' . $name . '"';

        if (isset($_REQUEST[$group]) && $_REQUEST[$group] == $name) {
            $html .= ' checked="checked"';
        }

        $this->ptln('<div class="radiobtn">', +2);
        $this->ptln($html . ' />');
        $this->ptln('<label for="' . $id . '">' . $this->getLang($label) . '</label>');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function getLang($id) {
        $string = $this->plugin->getLang($id);

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
    private function getLangPlural($id, $quantity) {
        if ($quantity == 1) {
            return $this->getLang($id . '#one', $quantity);
        }

        return $this->getLang($id . '#many', $quantity);
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

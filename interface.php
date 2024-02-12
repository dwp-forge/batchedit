<?php

/**
 * Plugin BatchEdit: User interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditMessage implements Serializable {
    const ERROR = 1;
    const WARNING = 2;

    public $type;
    public $data;

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

    /**
     *
     */
    public function getId() {
        return $this->data[0];
    }

    /**
     *
     */
    public function serialize() {
        return serialize(array($this->type, $this->data));
    }

    /**
     *
     */
    public function unserialize($data) {
        list($this->type, $this->data) = unserialize($data);
    }
}

class BatcheditErrorMessage extends BatcheditMessage {

    /**
     * Accepts message array that starts with message id followed by optional arguments.
     */
    public function __construct($message) {
        $this->type = self::ERROR;
        $this->data = $message;
    }
}

class BatcheditWarningMessage extends BatcheditMessage {

    /**
     * Accepts message array that starts with message id followed by optional arguments.
     */
    public function __construct($message) {
        $this->type = self::WARNING;
        $this->data = $message;
    }
}

class BatcheditInterface {

    private $plugin;
    private $indent;
    private $svgCache;

    /**
     *
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->indent = 0;
        $this->svgCache = array();
    }

    /**
     *
     */
    public function configure($config) {
        foreach ($config->getConfig() as $id => $value) {
            if (!empty($value) || $value === 0) {
                $_REQUEST[$id] = $value;
            }
            else {
                unset($_REQUEST[$id]);
            }
        }
    }

    /**
     *
     */
    public function printBeginning($sessionId) {
        print('<!-- batchedit -->');
        print('<div id="batchedit">');

        $this->printJavascriptLang();

        print('<form method="post">');
        print('<input type="hidden" name="session" value="' . $sessionId . '" />');
    }

    /**
     *
     */
    public function printEnding() {
        print('</form>');
        print('</div>');
        print('<!-- /batchedit -->');
    }

    /**
     *
     */
    public function printMessages($messages) {
        if (empty($messages)) {
            return;
        }

        print('<div id="be-messages">');

        foreach ($messages as $message) {
            print('<div class="' . $message->getClass() . '">');
            print($this->getLang($message->getFormatId(), call_user_func_array(array($this, 'getLang'), $message->data)));
            print('</div>');
        }

        print('</div>');
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

        print('<div id="be-totalstats"><div>');

        if ($editCount < $matchCount) {
            $this->printApplyCheckBox('be-applyall', $stats, 'ttl_applyall');
        }
        else {
            print($stats);
        }

        print('</div></div>');
    }

    /**
     *
     */
    public function printMatches($pages) {
        foreach ($pages as $page) {
            print('<div class="be-file">');

            $this->printPageStats($page);
            $this->printPageActions($page->getId());
            $this->printPageMatches($page);

            print('</div>');
        }
    }

    /**
     *
     */
    public function printMainForm($enableApply) {
        print('<div id="be-mainform">');

        print('<div>');

        print('<div id="be-editboxes">');
        print('<table>');

        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_search', 'search');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');

        print('</table>');
        print('</div>');

        $this->printOptions();

        print('</div>');

        // Value for this hidden input is set before submit through jQuery, containing
        // JSON-encoded list of all checked checkbox ids for single matches.
        // Consolidating these inputs into a single string variable avoids problems for
        // huge replacement sets exceeding `max_input_vars` in `php.ini`.
        print('<input type="hidden" name="apply" value="" />');

        print('<div id="be-submitbar">');

        $this->printSubmitButton('cmd[preview]', 'btn_preview');
        $this->printSubmitButton('cmd[apply]', 'btn_apply', $enableApply);

        print('<div id="be-progressbar">');
        print('<div id="be-progresswrap"><div id="be-progress"></div></div>');

        $this->printButton('cancel', 'btn_cancel');

        print('</div>');
        print('</div>');

        print('</div>');
    }

    /**
     *
     */
    private function printJavascriptLang() {
        print('<script type="text/javascript">');

        $langIds = array('hnt_textsearch', 'hnt_textreplace', 'hnt_regexpsearch', 'hnt_regexpreplace',
                'hnt_advregexpsearch', 'war_nosummary');
        $lang = array();

        foreach ($langIds as $id) {
            $lang[$id] = $this->getLang($id);
        }

        print('var batcheditLang = ' . json_encode($lang) . ';');
        print('</script>');
    }

    /**
     *
     */
    private function printApplyCheckBox($id, $label, $title, $checked = FALSE) {
        $checked = $checked ? ' checked="checked"' : '';

        print('<span class="be-apply" title="' . $this->getLang($title) . '">');
        print('<input type="checkbox" id="' . $id . '"' . $checked . ' />');
        print('<label for="' . $id . '">' . $label . '</label>');
        print('</span>');
    }

    /**
     *
     */
    private function printPageStats($page) {
        $stats = $this->getLang('sts_page', $page->getId(), $this->getLangPlural('sts_matches', count($page->getMatches())));

        print('<div class="be-stats">');

        if ($page->hasUnappliedMatches()) {
            $this->printApplyCheckBox($page->getId(), $stats, 'ttl_applyfile', !$page->hasUnmarkedMatches());
        }
        else {
            print($stats);
        }

        print('</div>');
    }

    /**
     *
     */
    private function printPageActions($pageId) {
        $link = wl($pageId);

        print('<div class="be-actions">');

        $this->printAction($link, 'ttl_view', 'file-document');
        $this->printAction($link . (strpos($link, '?') === FALSE ? '?' : '&') . 'do=edit', 'ttl_edit', 'pencil');
        $this->printAction('#be-mainform', 'ttl_mainform', 'arrow-down');

        print('</div>');
    }

    /**
     *
     */
    private function printAction($href, $titleId, $iconId) {
        $action = '<a class="be-action" href="' . $href . '" title="' . $this->getLang($titleId) . '">';
        $action .= $this->getSvg($iconId);
        $action .= '</a>';

        print($action);
    }

    /**
     *
     */
    private function printPageMatches($page) {
        foreach ($page->getMatches() as $match) {
            print('<div class="be-match">');

            $this->printMatchHeader($page->getId(), $match);
            $this->printMatchTable($match);

            print('</div>');
        }
    }

    /**
     *
     */
    private function printMatchHeader($pageId, $match) {
        $id = $pageId . '#' . $match->getPageOffset();

        print('<div class="be-matchid">');

        if (!$match->isApplied()) {
            $this->printApplyCheckBox($id, $id, 'ttl_applymatch', $match->isMarked());
        }
        else {
            // Add hidden checked checkbox to ensure that marked status is not lost on
            // applied matches if application is performed in multiple rounds. This can
            // be the case when one apply command is timed out and user issues a second
            // one to apply the remaining matches.
            print('<input type="checkbox" id="' . $id . '" checked="checked" style="display:none;" />');
            print($id);
        }

        print('</div>');
    }

    /**
     *
     */
    private function printMatchTable($match) {
        $original = $this->prepareText($match->getOriginalText(), $match->isApplied() ? ' be-replaced' : 'be-preview');
        $replaced = $this->prepareText($match->getReplacedText(), $match->isApplied() ? ' be-applied' : 'be-preview');
        $before = $this->prepareText($match->getContextBefore());
        $after = $this->prepareText($match->getContextAfter());

        print('<table><tr>');
        print('<td class="be-text">' . $before . $original . $after . '</td>');
        print('<td class="be-arrow">' . $this->getSvg('slide-arrow-right') . '</td>');
        print('<td class="be-text">' . $before . $replaced . $after . '</td>');
        print('</tr></table>');
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
        print('<tr>');
        print('<td class="be-title">' . $this->getLang($title) . '</td>');
        print('<td class="be-edit">');

        switch ($name) {
            case 'namespace':
                $this->printEditBox($name);
                break;

            case 'search':
            case 'replace':
                $multiline = isset($_REQUEST['multiline']);
                $placeholder = $this->getLang($this->getPlaceholderId($name));

                $this->printEditBox($name, FALSE, TRUE, !$multiline, $placeholder);
                $this->printTextArea($name, $multiline, $placeholder);
                break;

            case 'summary':
                $this->printEditBox($name);
                $this->printCheckBox('minor', 'lbl_minor');
                break;
        }

        print('</td>');
        print('</tr>');
    }

    /**
     *
     */
    private function getPlaceholderId($editName) {
        switch ($editName) {
            case 'search':
                switch ($_REQUEST['searchmode']) {
                    case 'text':
                        return 'hnt_textsearch';
                    case 'regexp':
                        return isset($_REQUEST['advregexp']) ? 'hnt_advregexpsearch' : 'hnt_regexpsearch';
                }
            case 'replace':
                return 'hnt_' . $_REQUEST['searchmode'] . 'replace';
        }

        return '';
    }

    /**
     *
     */
    private function printOptions() {
        $style = 'min-width: ' . $this->getLang('dim_options') . ';';

        print('<div id="be-options" style="' . $style . '">');

        print('<div class="be-radiogroup">');
        print('<div>' . $this->getLang('lbl_searchmode') . '</div>');

        $this->printRadioButton('searchmode', 'text', 'lbl_searchtext');
        $this->printRadioButton('searchmode', 'regexp', 'lbl_searchregexp');

        print('</div>');

        $this->printCheckBox('matchcase', 'lbl_matchcase');
        $this->printCheckBox('multiline', 'lbl_multiline');

        print('</div>');

        print('<div class="be-actions">');

        $this->printAction('javascript:openAdvancedOptions();', 'ttl_extoptions', 'settings');

        print('</div>');

        $style = 'width: ' . $this->getLang('dim_extoptions') . ';';

        print('<div id="be-extoptions" style="' . $style . '">');
        print('<div class="be-actions">');

        $this->printAction('javascript:closeAdvancedOptions();', '', 'close');

        print('</div>');

        $this->printCheckBox('advregexp', 'lbl_advregexp');
        $this->printCheckBox('matchctx', 'printMatchContextLabel');
        $this->printCheckBox('searchlimit', 'printSearchLimitLabel');
        $this->printCheckBox('keepmarks', 'printKeepMarksLabel');
        $this->printCheckBox('tplpatterns', 'lbl_tplpatterns');
        $this->printCheckBox('checksummary', 'lbl_checksummary');

        print('</div>');
    }

    /**
     *
     */
    private function printMatchContextLabel() {
        $label = preg_split('/(\{\d\})/', $this->getLang('lbl_matchctx'), -1, PREG_SPLIT_DELIM_CAPTURE);
        $edits = array('{1}' => 'ctxchars', '{2}' => 'ctxlines');

        $this->printLabel('matchctx', $label[0]);
        $this->printEditBox($edits[$label[1]], TRUE, isset($_REQUEST['matchctx']));
        $this->printLabel('matchctx', $label[2]);
        $this->printEditBox($edits[$label[3]], TRUE, isset($_REQUEST['matchctx']));
        $this->printLabel('matchctx', $label[4]);
    }

    /**
     *
     */
    private function printSearchLimitLabel() {
        $label = explode('{1}', $this->getLang('lbl_searchlimit'));

        $this->printLabel('searchlimit', $label[0]);
        $this->printEditBox('searchmax', TRUE, isset($_REQUEST['searchlimit']));
        $this->printLabel('searchlimit', $label[1]);
    }

    /**
     *
     */
    private function printKeepMarksLabel() {
        $label = explode('{1}', $this->getLang('lbl_keepmarks'));
        $disabled = isset($_REQUEST['keepmarks']) ? '' : ' disabled="disabled"';

        $this->printLabel('keepmarks', $label[0]);

        print('<select name="markpolicy"' . $disabled . '>');

        for ($i = 1; $i <= 4; $i++) {
            $selected = $_REQUEST['markpolicy'] == $i ? ' selected="selected"' : '';

            print('<option value="' . $i . '"' . $selected . '>' . $this->getLang('lbl_keepmarks' . $i) . '</option>');
        }

        print('</select>');

        $this->printLabel('keepmarks', $label[1]);
    }

    /**
     *
     */
    private function printEditBox($name, $submitted = TRUE, $enabled = TRUE, $visible = TRUE, $placeholder = '') {
        $html = '<input type="text" class="be-edit" id="be-' . $name . 'edit"';

        if ($submitted) {
            $html .= ' name="' . $name . '"';
        }

        if (!empty($placeholder)) {
            $html .= ' placeholder="' . $placeholder . '"';
        }

        if (($submitted || $visible) && isset($_REQUEST[$name])) {
            $html .= ' value="' . htmlspecialchars($_REQUEST[$name]) . '"';
        }

        if (!$enabled) {
            $html .= ' disabled="disabled"';
        }

        if (!$visible) {
            $html .= ' style="display: none;"';
        }

        print($html . ' />');
    }

    /**
     *
     */
    private function printTextArea($name, $visible = TRUE, $placeholder = '') {
        $html = '<textarea class="be-edit" id="be-' . $name . 'area" name="' . $name . '"';

        if (!empty($placeholder)) {
            $html .= ' placeholder="' . $placeholder . '"';
        }

        $style = array();

        if (!$visible) {
            $style[] = 'display: none;';
        }

        if (isset($_REQUEST[$name . 'height'])) {
            $style[] = 'height: ' . $_REQUEST[$name . 'height'] . 'px;';
        }

        if (!empty($style)) {
            $html .= ' style="' . join(' ', $style) . '"';
        }

        $html .= '>';

        if (isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];

            // HACK: It seems that even with "white-space: pre" textarea trims one leading
            // empty line. To workaround this duplicate the empty line.
            if (preg_match("/^(\r?\n)/", $value, $match) == 1) {
                $value = $match[1] . $value;
            }

            $html .= htmlspecialchars($value);
        }

        print($html . '</textarea>');
    }

    /**
     *
     */
    private function printCheckBox($name, $label) {
        $html = '<input type="checkbox" id="be-' . $name . '" name="' . $name . '" value="on"';

        if (isset($_REQUEST[$name])) {
            $html .= ' checked="checked"';
        }

        print('<div class="be-checkbox">');
        print($html . ' />');

        $this->printLabel($name, $label);

        print('</div>');
    }

    /**
     *
     */
    private function printRadioButton($group, $name, $label) {
        $id = $group . $name;
        $html = '<input type="radio" id="be-' . $id . '" name="' . $group . '" value="' . $name . '"';

        if (isset($_REQUEST[$group]) && $_REQUEST[$group] == $name) {
            $html .= ' checked="checked"';
        }

        print('<div class="be-radiobtn">');
        print($html . ' />');

        $this->printLabel($id, $label);

        print('</div>');
    }

    /**
     *
     */
    private function printLabel($name, $label) {
        if (substr($label, 0, 5) == 'print') {
            $this->$label();
        }
        else {
            if (substr($label, 0, 4) == 'lbl_') {
                $label = $this->getLang($label);
            }
            else {
                $label = trim($label);
            }

            if (!empty($label)) {
                print('<label for="be-' . $name . '">' . $label . '</label>');
            }
        }
    }

    /**
     *
     */
    private function printSubmitButton($name, $label, $enabled = TRUE) {
        $html = '<input type="submit" class="button be-button be-submit" name="' . $name . '" value="' . $this->getLang($label) . '"';

        if (!$enabled) {
            $html .= ' disabled="disabled"';
        }

        print($html . ' />');
    }

    /**
     *
     */
    private function printButton($name, $label) {
        print('<input type="button" class="button be-button" name="' . $name . '" value="' . $this->getLang($label) . '" />');
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
        $lang = $this->getLang($id . $this->getPluralForm($quantity), $quantity);

        if (!empty($lang)) {
            return $lang;
        }

        return $this->getLang($id . '#many', $quantity);
    }

    /**
     *
     */
    private function getPluralForm($quantity) {
        global $conf;

        if ($conf['lang'] == 'ru') {
            $quantity %= 100;

            if ($quantity >= 5 && $quantity <= 20) {
                return '#many';
            }

            $quantity %= 10;

            if ($quantity >= 2 && $quantity <= 4) {
                return '#few';
            }
        }

        if ($quantity == 1) {
            return '#one';
        }

        return '#many';
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

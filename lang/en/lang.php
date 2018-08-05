<?php
/**
 * Plugin BatchEdit: English language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

// Settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// For admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'BatchEdit';

$lang['err_invreq'] = 'Invalid request.';
$lang['err_nosearch'] = 'Search expression is not specified.';
$lang['err_invregexp'] = 'Invalid regular expression.';
$lang['err_emptyidx'] = 'The page index is empty.';
$lang['err_idxaccess'] = 'Cannot access the page index.';
$lang['err_emptyns'] = 'The pages found in namespace "{1}".';
$lang['err_pregfailed'] = 'Regular expression matching has failed.';

$lang['war_nomatches'] = 'No matches are found.';
$lang['war_norights'] = 'You have no rights to edit page {1}.';
$lang['war_pagelock'] = 'Page {1} is locked by {2}.';
$lang['war_matchfail'] = 'Failed to apply match {1}.';
$lang['war_searchlimit'] = 'The search was interrupted after reaching maximal number of matches.';
$lang['war_timeout'] = 'The operation was interrupted because it is taking too much time.';
$lang['war_cancelled'] = 'The operation was interrupted on user\'s request.';
$lang['war_nosummary'] = 'The edit summary was not provided. Do you want to proceed witout it?';

$lang['msg_error'] = '<b>Error:</b> {1}';
$lang['msg_warning'] = '<b>Warning:</b> {1}';

$lang['btn_preview'] = 'Preview';
$lang['btn_apply'] = 'Apply';
$lang['btn_cancel'] = 'Cancel';

$lang['hnt_textsearch'] = 'AnyWiki';
$lang['hnt_textreplace'] = 'DokuWiki';
$lang['hnt_regexpsearch'] = '\w+(Wiki)';
$lang['hnt_regexpreplace'] = 'Doku$1';
$lang['hnt_advregexpsearch'] = '/\w+(Wiki)/m';

$lang['lbl_ns'] = 'Namespace';
$lang['lbl_search'] = 'Search for';
$lang['lbl_replace'] = 'Replace with';
$lang['lbl_summary'] = 'Edit summary';
$lang['lbl_minor'] = 'Minor changes';
$lang['lbl_searchmode'] = 'Search mode';
$lang['lbl_searchtext'] = 'Plain text';
$lang['lbl_searchregexp'] = 'Regular expression';
$lang['lbl_matchcase'] = 'Case sensitive';
$lang['lbl_multiline'] = 'Multiline';
$lang['lbl_advregexp'] = 'Use delimiters and modifiers in regular expression';
$lang['lbl_matchctx'] = 'Show match context of {1} characters or {2} lines';
$lang['lbl_searchlimit'] = 'Stop search after finding first {1} matches';
$lang['lbl_keepmarks'] = 'Preserve marked matches on preview {1}';
$lang['lbl_keepmarks1'] = 'Safe mode';
$lang['lbl_keepmarks2'] = 'Same match';
$lang['lbl_keepmarks3'] = 'Same offset';
$lang['lbl_keepmarks4'] = 'I feel lucky!';
$lang['lbl_checksummary'] = 'Show confirmation on applying edits with no summary';
$lang['lbl_searching'] = 'Searching...';
$lang['lbl_applying'] = 'Applying...';

$lang['sts_preview'] = 'Search results: {1} on {2}';
$lang['sts_apply'] = 'Edit results: {1} on {2}, {3}';
$lang['sts_page'] = '{1} &ndash; {2}';
$lang['sts_matches#one'] = '{1} match';
$lang['sts_matches#many'] = '{1} matches';
$lang['sts_pages#one'] = '{1} page';
$lang['sts_pages#many'] = '{1} pages';
$lang['sts_edits#one'] = '{1} replacement applied';
$lang['sts_edits#many'] = '{1} replacements applied';

$lang['ttl_applyall'] = 'Apply all matches';
$lang['ttl_applyfile'] = 'Apply all matches in this file';
$lang['ttl_applymatch'] = 'Apply this match';
$lang['ttl_view'] = 'Go to this page';
$lang['ttl_edit'] = 'Edit this page';
$lang['ttl_mainform'] = 'Go to bottom';
$lang['ttl_extoptions'] = 'Advanced options';

$lang['dim_options'] = '13em';
$lang['dim_extoptions'] = '28em';

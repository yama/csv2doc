//<?php
/**
 * Csv2Doc MODx module - module
 *
 * @package Csv2Doc
 * @author Kazuyuki Ikeda (HIKIDAS Co.,Ltd)	
 * @link http://www.hikidas.com/
 * @version 0.9.3b2
 */

global	$SystemAlertMsgQueque, $_lang, $manager_theme, $modx_charset;
global	$manager_language;
//---- for Evo
global	$_style;
global	$modx_textdir, $modx_manager_charset, $modx_lang_attribute;
//----
include_once "header.inc.php";	// The common header of the MODx manager

global	$modx, $default_template;

$modulePath = $modx->config['base_path'].'assets/modules/csv2doc/';
include_once $modulePath.'Csv2Doc.class.inc.php';
include_once $modulePath.'HTMLblock.class.inc.php';
include_once $modulePath.'Parameter.class.inc.php';

function trc($x) {
	$da = debug_backtrace();
	echo $da[0]['file'].'('.$da[0]['line'].')'.time()."<pre>\n";
	if (! is_null($x)) {
		var_dump($x);
	}
	echo "</pre>\n";
}

/***********************************************************************
	Global vars
***********************************************************************/
global $c2d_error; $c2d_error = FALSE;			// Error flag
global $c2d_msg_tags; $c2d_msg_tags = array();	// Message Queue

/***********************************************************************
	Setting parameters
***********************************************************************/
/*
モジュール設定の例
&doc_parent=親フォルダID;int;1 &doc_template=テンプレートID;int;1 &matching_fieldname=識別フィールド;string;menuindex &delete_in_parent=フォルダ内の不要なレコードを削除する;int;1 &require_fieldnames=必須フィールド;string;menuindex,pagetitle &runparams=実行時パラメータ;string;csv_fname
*/
$params = new ParamContainer();
$params->add('my_name',
	new tParam('モジュールの名前', 'Csv2Doc'));
$params->add('runparams',
	new tParam('実行時パラメータ',
		'doc_parent,doc_template,matching_fieldname,delete_in_parent,set_pub_date,csv_fname,csv_skiplines,csv_encoding,num_verify_cols'));
$params->add('matching_fieldname',
	new tParam('更新用一致判定フィールド'));
$params->add('require_fieldnames',
	new tParam('必須フィールド名（カンマ区切り）'));
$params->add('num_verify_cols',
	new nParam('確認表示列数', 8));
$params->add('verify_fieldnames',
	new tParam('確認表示フィールド名（カンマ区切り）'));
$params->add('csv_only_data',
	new fParam('CSVファイルはヘッダ行を含まない'));
$params->add('csv_fieldnames',
	new tParam('フィールド名（カンマ区切り）'));
$params->add('csv_delimiter',
	new tParam('フィールド区切り記号', ','));
$params->add('csv_enclosure',
	new tParam('フィールド囲い込み記号', '"'));
$params->add('csv_maxreclen',
	new nParam('行最大長', 40960));
$params->add('sys_encoding',
	new tParam('MODxのエンコーディング', 'UTF-8'));
$params->add('sys_locale',
	new tParam('MODxのロケール', 'ja_JP.UTF-8'));
$params->add('parent_alias_fieldname',
	new tParam('ドキュメントを作成するフォルダのエイリアス用フィールド名'));
	// これを指定した場合、doc_parent直下でエイリアスを探す。
$params->add('doc_parent',
	new nParam('ドキュメントを作成するフォルダのドキュメントID', 1));
	// parent_alias_fieldname指定時は、親の親の指定となる。
$p = new sParam('ドキュメントに使用するテンプレートID', $default_template);
	$params->add('doc_template', $p);
	$p->setOptList(get_templates_hash());
	unset($p);
$params->add('doc_alias',
	new tParam('デフォルトドキュメントエイリアス'));
$params->add('csv_fname',
	new tParam('アップロードされたCSVファイルのファイル名', 'data.csv'));
$params->add('csv_dname',
	new tParam('アップロードされたCSVファイルのディレクトリ名', 'assets/files/'));
$params->add('csv_skiplines',
	new nParam('2行目からデータレコードまで読み飛ばす行数', 1));
$p = new sParam('CSVファイルエンコーディング', 'SJIS');
	$params->add('csv_encoding', $p);
	$p->setOptList(mb_encode_list());
	unset($p);
$params->add('delete_in_parent',
	new fParam('フォルダ内の不要なレコードを削除する', 0));
$params->add('nl2br',
	new fParam('改行時にbrタグを挿入する', 1));
$params->add('nl2br_fieldnames',
	new tParam('改行時にbrタグを挿入するフィールド（カンマ区切り）'));
$p = new sParam('デフォルトドキュメントタイプ', 'document');
	$params->add('doc_type', $p);
	$doc_type_options = array('document', 'reference');
	$p->setOptList($doc_type_options);
	unset($p);
$params->add('doc_contentType',
	new tParam('デフォルトコンテントタイプ', 'text/html'));
$params->add('doc_published',
	new fParam('デフォルトで公開する', 1));
$params->add('doc_privatemgr',
	new fParam('管理画面プライベートにする', 0));
$params->add('set_pub_date',
	new fParam('デフォルトで公開日時に現在時刻を設定する'));
//---- 別フィールド名関連 ----
$params->add('byname_type',
	new tParam('typeフィールド'));
$params->add('byname_contentType',
	new tParam('contentTypeフィールド'));
$params->add('byname_pagetitle',
	new tParam('pagetitleフィールド'));
$params->add('byname_longtitle',
	new tParam('longtitleフィールド'));
$params->add('byname_description',
	new tParam('descriptionフィールド'));
$params->add('byname_alias',
	new tParam('aliasフィールド'));
$params->add('byname_link_attributes',
	new tParam('link_attributesフィールド'));
$params->add('byname_published',
	new tParam('publishedフィールド'));
$params->add('byname_pub_date',
	new tParam('pub_dateフィールド'));
$params->add('byname_unpub_date',
	new tParam('unpub_dateフィールド'));
$params->add('byname_parent',
	new tParam('parentフィールド'));
$params->add('byname_isfolder',
	new tParam('isfolderフィールド'));
$params->add('byname_content',
	new tParam('contentフィールド'));
$params->add('byname_richtext',
	new tParam('richtextフィールド'));
$params->add('byname_template',
	new tParam('templateフィールド'));
$params->add('byname_menuindex',
	new tParam('menuindexフィールド'));
$params->add('byname_searchable',
	new tParam('searchableフィールド'));
$params->add('byname_cacheable',
	new tParam('cacheableフィールド'));
$params->add('byname_createdby',
	new tParam('createdbyフィールド'));
$params->add('byname_createdon',
	new tParam('createdonフィールド'));
$params->add('byname_editedby',
	new tParam('editedbyフィールド'));
$params->add('byname_editedon',
	new tParam('editedonフィールド'));
$params->add('byname_deleted',
	new tParam('deletedフィールド'));
$params->add('byname_deletedon',
	new tParam('deletedonフィールド'));
$params->add('byname_deletedby',
	new tParam('deletedbyフィールド'));
$params->add('byname_publishedon',
	new tParam('publishedonフィールド'));
$params->add('byname_publishedby',
	new tParam('publishedbyフィールド'));
$params->add('byname_menutitle',
	new tParam('menutitleフィールド'));
$params->add('byname_donthit',
	new tParam('donthitフィールド'));
$params->add('byname_haskeywords',
	new tParam('haskeywordsフィールド'));
$params->add('byname_hasmetatags',
	new tParam('hasmetatagsフィールド'));
$params->add('byname_privateweb',
	new tParam('privatewebフィールド'));
$params->add('byname_privatemgr',
	new tParam('privatemgrフィールド'));
$params->add('byname_content_dispo',
	new tParam('content_dispoフィールド'));
$params->add('byname_hidemenu',
	new tParam('hidemenuフィールド'));
//---- 表示制御 ----
$params->add('debug',
	new fParam('デバッグフラグ', 0));	// （未使用）
$params->add('form_gen_class',
	new tParam('入力フォーム・タグ生成クラス名', 'HTMLtable'));
$params->add('form_gen_param',
	new tParam('入力フォーム・タグ生成パラメータ', 'border cellpadding="4"'));
$params->add('form_gen_cols',
	new nParam('入力フォーム・タグ生成列数'));

$params->add('brothers_gen_class',
	new tParam('削除レコード一覧・タグ生成クラス名', 'HTMLtable'));
$params->add('brothers_gen_param',
	new tParam('削除レコード一覧・タグ生成パラメータ', 'border cellpadding="4"'));
$params->add('brothers_gen_cols',
	new nParam('削除レコード一覧・タグ生成列数'));

$params->add('fields_gen_class',
	new tParam('フィールド一覧・タグ生成クラス名', 'HTMLtable'));
$params->add('fields_gen_param',
	new tParam('フィールド一覧・タグ生成パラメータ', 'border cellpadding="4"'));
$params->add('fields_gen_cols',
	new nParam('フィールド一覧・タグ生成列数'));

$params->add('records_gen_class',
	new tParam('データレコード一覧・タグ生成クラス名', 'HTMLtable'));
$params->add('records_gen_param',
	new tParam('データレコード一覧・タグ生成パラメータ', 'border cellpadding="4"'));
$params->add('records_gen_cols',
	new nParam('データレコード一覧・タグ生成列数'));

// Set configuration variables (from the control panel of MODx manager)
$params->set_configuration(compact($params->keys()));

//----
// Check the user privileges for save
if (! $modx->hasPermission('save_document')) {
	$e->setError(3);	// You don't have enough privileges for this action!
	$e->dumpError();	// display message & return to previous webpage
} else {
	// execution
	$csv2doc = new Csv2Doc($params);
	$csv2doc->execute();
}

include_once "footer.inc.php";	// The common footer of the MODx manager


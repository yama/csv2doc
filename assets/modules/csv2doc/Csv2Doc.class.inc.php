<?php

/**
 * Csv2Doc MODx module - Main Class
 *
 * @package Csv2Doc
 * @author Kazuyuki Ikeda (HIKIDAS Co.,Ltd)	
 * @link http://www.hikidas.com/
 * @version 0.9.3b2
 */
class Csv2Doc
{
    var $params;            // 設定パラメータ（コンテナ）
    var $now;                // 現在日時（UNIXタイム）
    var $login_id;            // ログインユーザID
    var $tmplvars_id;        // テンプレート変数名 => テンプレート変数ID
    var $bynames;            // 別フィールド名配列
    var $checkedDocuments;    // アクセス許可チェック済みドキュメントID
    var $permNewDocument;    // 新規ドキュメント作成権限フラグ
    var $permEditDocument;    // ドキュメント編集権限フラグ
    var $permPubDocument;    // ドキュメント公開権限フラグ
    var $default_doc;        // デフォルトフィールド
    var $default_publish;    // デフォルト公開関連フィールド
    var $default_insert;    // デフォルト挿入用フィールド
    var $default_update;    // デフォルト更新用フィールド
    var $default_delete;    // デフォルト削除用フィールド
    var $matching_values;    // 全データの更新一致判定フィールドの値
    // （データの値 => ドキュメントID）
    var $matching_table;    // 更新一致判定用テーブル種別（docvars／tmplvars）
    var $matching_tmplvarid; // 更新一致判定フィールドのテンプレート変数ID
    var $brothers;            // 同一フォルダ内データ
    var $matched_documents;    // 更新一致ドキュメントデータ
    var $curErr;            // 直近のエラーメッセージ
    var $curMsg;
    var $curErrLevel;        // 直近のエラーレベル
    var $curErrParam;        // 直近のエラーのパラメータ（name => value）
    var $curColor;
    var $act;                // 処理コマンド（confirm／save／new：デフォルト）
    var $fidx;                // 処理中のフィールド添字（0～）
    var $fstatus;            // フィールド状態表示用（status,color,...）
    var $rec;                // 処理中のレコード
    var $rec_no;            // 処理中のレコード番号（1～）
    var $ids;                // 更新対象ドキュメントID配列（新規作成時：FALSE）
    var $docid;                // 作成したドキュメントID
    var $parent;            // 検索した親ドキュメントID
    var $result;            // レコード処理結果表示用（status,color,...）
    var $verify_fieldnames;    // 確認表示フィールド名配列
    var $require_fieldnames; // 必須フィールド名配列
    var $completed;            // 処理完了フラグ

    var $form_gen;            // 入力フォーム・タグ生成オブジェクト
    var $brothers_gen;    // 削除レコード一覧・タグ生成オブジェクト
    var $fields_gen;    // フィールド一覧・タグ生成オブジェクト
    var $records_gen;    // データレコード一覧・タグ生成オブジェクト

    var $tbl_content; // ドキュメントテーブル
    var $tbl_tmplvars; // テンプレート変数テーブル
    var $tbl_tmplvar_contvals; // テンプレート変数の値テーブル
    var $meta_content; // メタデータ
    var $docvars_fidx; // ドキュメント変数名 => フィールド添字
    var $tmplvars_fidx; // テンプレート変数ID => フィールド添字
    var $tmplvars_fnames; // テンプレート変数名 => フィールド添字
    var $nl2br; // 改行を<br>に変換するかどうかのフラグ
    var $nl2br_fieldnames; // 改行を<br>に変換するフィールド名配列
    var $conf_fields_tags; // フィールドタグ
    var $conf_records_tags; // レコードタグ
    var $conf_brothers_tags; // 兄弟タグ
    var $form_tags; // フォームタグ
    var $runparams; // 実行パラメータ
    var $verify_cols; // 確認表示列数
    public $csv; // CSVファイル操作オブジェクト

    //==================================================================
    // Constructor
    function __construct(&$params)
    {
        $this->params = &$params;
        $this->completed = FALSE;
    }

    //==================================================================
    // get parameter value
    function paramV($name)
    {
        $p = &$this->params->element($name);
        $ret = $p->getVal();
        unset($p);
        return $ret;
    }
    // get parameter object
    function &param($name)
    {
        return $this->params->element($name);
    }

    //==================================================================
    // Execution
    function execute()
    {
        global $c2d_error;    // Error flag
        $this->getquery();
        if ($this->act != 'new') {
            if (! $c2d_error) {
                $this->initialize();
            }
            if (! $c2d_error) {
                $this->maketemporary();
            }
            if (! $c2d_error) {
                $this->init_fields_gen();
                $this->loadheader();
                //---- 確認表示フィールド名の初期化
                $this->init_verify_fields();
                $this->init_require_fieldnames();
            }
            if (! $c2d_error) {
                $this->matching();
            }
            if (! $c2d_error) {
                $this->getbrothers();
            }
            if (! $c2d_error) {
                $this->init_records_gen();
                $this->loaddata();
            }
            if (! $c2d_error && $this->paramV('delete_in_parent')) {
                $this->init_brothers_gen();
                $this->deletebrothers();
            }
            if ($this->completed) {
                evo()->clearCache();
                $this->reload();
            }
        }
        $this->init_form_gen();
        $this->makeform();
        $this->output();
    }

    //==================================================================
    // get action & runtime parameters
    function getquery()
    {
        //---- judge the action
        $act = 'new';            // 新規入力フォーム
        if (! empty($_POST['confirm'])) {
            $act = 'confirm';    // 入力確認
        } elseif (! empty($_POST['save'])) {
            $act = 'save';        // 保存
        }
        $this->act = $act;
        //---- get runtime parameters0
        $this->params->get_request($this->paramV('runparams'), 'POST');
    }

    //==================================================================
    // Initialize properties
    function initialize()
    {
        global    $modx;
        $this->now = time();                        // 現在日時の取得
        $this->login_id = $modx->getLoginUserID();    // ユーザIDの取得

        $this->checkedDocuments = array();
        $this->permNewDocument = $modx->hasPermission('new_document');
        $this->permEditDocument = $modx->hasPermission('edit_document');
        $this->permPubDocument = $modx->hasPermission('publish_document');

        //---- 
        $this->set_default_doc();

        //---- テーブル名取得
        $this->tbl_content = $modx->getFullTableName('site_content');    // ドキュメント
        $this->tbl_tmplvars = $modx->getFullTableName('site_tmplvars');    // テンプレート変数登録
        $this->tbl_tmplvar_contvals = $modx->getFullTableName('site_tmplvar_contentvalues');    // テンプレート変数の値

        //---- メタデータの取得
        $this->meta_content = $modx->db->getTableMetaData($this->tbl_content);

        //---- テンプレート変数名とIDの対応表生成
        $this->set_tmplvars_id();

        //---- 
        $this->init_nl2br();
    }

    //==================================================================
    function set_default_doc()
    {
        global    $modx;
        $this->default_doc = array(
            'parent'        => $modx->db->escape($this->paramV('doc_parent')),
            'alias'            => $modx->db->escape($this->paramV('doc_alias')),
            'template'        => $modx->db->escape($this->paramV('doc_template')),
            'type'            => $modx->db->escape($this->paramV('doc_type')),
            'contentType'    => $modx->db->escape($this->paramV('doc_contentType')),
            'privatemgr'    => $modx->db->escape($this->paramV('doc_privatemgr')),
        );
        if ($this->paramV('doc_published') !== '') {
            $this->default_doc['published'] = $modx->db->escape($this->paramV('doc_published'));
        }
        $this->default_publish = array(
            'publishedby'    => $this->login_id,
            'publishedon'    => $this->now,
        );
        $this->default_insert = array(
            'createdby'        => $this->login_id,
            'createdon'        => $this->now,
        );
        $this->default_update = array(
            'editedby'        => $this->login_id,
            'editedon'        => $this->now,
        );
        $this->default_delete = array(
            'deleted'        => '1',
            'deletedby'        => $this->login_id,
            'deletedon'        => $this->now,
        );
    }

    //==================================================================
    // テンプレート変数名とIDの対応表生成
    function set_tmplvars_id()
    {
        global    $modx;
        $drs = $modx->db->select('id,name', $this->tbl_tmplvars);
        $tmplvar_recs = $modx->db->makeArray($drs);
        $this->tmplvars_id = array();        // テンプレート変数名 => テンプレート変数ID
        foreach ($tmplvar_recs as $tmplvars_rec) {
            $this->tmplvars_id[$tmplvars_rec['name']] = $tmplvars_rec['id'];
        }
    }

    //==================================================================
    function init_nl2br()
    {
        if (! $this->paramV('nl2br')) {
            $this->nl2br = FALSE;
            return;
        }
        $this->nl2br = TRUE;
        $nl2br_fieldnames = trim($this->paramV('nl2br_fieldnames'));
        if ($nl2br_fieldnames !== '') {
            $this->nl2br_fieldnames = explode(',', $nl2br_fieldnames);
        } else {
            $this->nl2br_fieldnames = FALSE;
        }
    }

    //==================================================================
    // convert & make temporary file or open file
    function maketemporary()
    {
        $this->csv = new Csv($this->params);
        $csv_path = '';
        if (! $this->csv->open($csv_path)) {
            return;
        }
    }

    //==================================================================
    function init_fields_gen($gen_cols = '')
    {
        $gen_name = $this->paramV('fields_gen_class');
        $gen_param = $this->paramV('fields_gen_param');
        if ($gen_cols === '') {
            $gen_cols = $this->paramV('fields_gen_cols');
        }
        if ($gen_cols === '') {
            $gen_cols = $this->paramV('csv_skiplines') + 2;
        }
        $this->fields_gen = new $gen_name($gen_cols, $gen_param);
    }

    //==================================================================
    function loadheader()
    {
        if (! $this->csv->load_header()) {
            return;
        }
        $fieldnames = $this->csv->fieldnames;
        $this->init_bynames();
        if (! in_array('parent', $fieldnames) && empty($this->bynames['parent'])) {
            // ドキュメントを作成する場所(親ドキュメント)に対するアクセス許可があるかチェック
            if (! $this->check_parent_perm($this->paramV('doc_parent'))) {
                if (! $this->curErrLevel) {
                    $this->curErrLevel = "MSG_ERROR";
                }
                putMsg($this->curErrLevel, $this->make_errmsg());
                return;
            }
        }
        // デフォルトで公開、もしくは、CSVに関連フィールドがある場合、公開権限があるかチェック
        if (! $this->permPubDocument) {
            if (
                $this->paramV('doc_published')
                || in_array('published', $fieldnames) || isset($this->bynames['published'])
                || in_array('pub_date', $fieldnames) || isset($this->bynames['pub_date'])
                || in_array('unpub_date', $fieldnames) || isset($this->bynames['unpub_date'])
            ) {
                if (! $this->curErrLevel) {
                    $this->curErrLevel = "MSG_ERROR";
                }
                $this->curErr = '公開権限無し';
                putMsg($this->curErrLevel, $this->make_errmsg());
                return;
            }
        }
        //---- フィールドのチェック
        //---- ドキュメント変数名とフィールド添え字（0～）の対応表生成
        $this->docvars_fidx = array();    // ドキュメント変数名 => フィールド添字
        //---- テンプレート変数名とフィールド添え字（0～）の対応表生成
        $this->tmplvars_fidx = array();    // テンプレート変数ID => フィールド添字
        $this->tmplvars_fnames = array();    // テンプレート変数名 => フィールド添字

        for ($this->fidx = 0; $this->fidx < count($fieldnames); $this->fidx++) {
            $this->init_fstatus();
            $fn = $fieldnames[$this->fidx];
            $this->check_field($fn);
            $this->check_error_field($fn);
            $this->tag_gen_fields($fn);
            $this->tag_gen_skiped_lines();
            $this->fields_gen->nextRow();
        }
        $conf_tags = '';
        $conf_tags .= $this->section_header('フィールド一覧');
        $conf_tags .= $this->fields_gen->get();
        $conf_tags .= $this->section_footer();
        $this->conf_fields_tags = $conf_tags;
    }
    //==================================================================
    // 別フィールド名配列の初期化
    function init_bynames()
    {
        $this->bynames = array();
        $param_keys = $this->params->keys();
        foreach ($param_keys as $paramkey) {
            if (substr($paramkey, 0, 7) != 'byname_') {
                continue;
            }
            $fn = $this->paramV($paramkey);
            if ($fn === '') {
                continue;
            }
            $target_field = substr($paramkey, 7);
            if (! in_array($fn, $this->csv->fieldnames)) {
                putMsg("MSG_WARNING", $target_field . 'フィールド(' . $fn . ')は未定義です。');
                continue;
            }
            $this->bynames[$target_field] = $fn;
        }
    }

    //==================================================================
    function init_fstatus()
    {
        $this->fstatus = array('status' => '', 'color' => '');
    }
    function tag_gen_fields($fn)
    {
        $this->fields_gen->sCol($fn);
        $this->fields_gen->col($this->fstatus['status'], $this->fstatus['color']);
    }
    function tag_gen_skiped_lines()
    {
        foreach ($this->csv->skiped_lines as $sl) {
            $this->fields_gen->col($sl[$this->fidx]);
        }
    }

    //==================================================================
    function check_field($fn)
    {
        if ($fn === "") {
            $this->fstatus['status'] .= '(対象外：フィールド名未入力)';
            $this->fstatus['color'] = 'gray';
        } elseif (isset($this->meta_content[$fn])) {
            $this->docvars_fidx[$fn] = $this->fidx;
            $this->fstatus['status'] .= 'ドキュメント変数';
        } elseif (isset($this->tmplvars_id[$fn])) {
            $this->tmplvars_fidx[$this->tmplvars_id[$fn]] = $this->fidx;
            $this->tmplvars_fnames[] = $fn;
            $this->fstatus['status'] .= 'テンプレート変数';
            $this->fstatus['color'] = 'blue';
        }
    }

    //==================================================================
    function check_error_field($fn, $msg = NULL)
    {
        if (is_null($msg)) {
            $msg = '未定義：ドキュメント変数、テプレート変数に該当無し';
        }
        if ($this->fstatus['status'] === '') {
            putMsg("MSG_WARNING", 'フィールド名(' . $fn . ')は未定義です。');
            $this->fstatus['status'] = $msg;
            $this->fstatus['color'] = 'red';
            return TRUE;
        }
        return FALSE;
    }

    //==================================================================
    //	check_parent_perm
    //	親ドキュメントへのアクセス権限のチェック
    //==================================================================
    function check_parent_perm($parent)
    {
        global    $modx;
        if (! in_array($parent, $this->checkedDocuments)) {
            if (! checkUserDocPerm($parent)) {
                $this->curErr = '親ドキュメントにアクセス権限無し';
                $this->curErrParam['parent'] = $parent;
                return FALSE;
            } else {
                $this->checkedDocuments[] = $parent;
            }
            if (! $this->set_folder_parent($parent)) {
                $this->curErr = '親ドキュメントへのフォルダ設定でエラー';
                $this->curErrParam['parent'] = $parent;
                $this->curErrParam['error'] = $modx->db->getLastError();
                return FALSE;
            }
        }
        return TRUE;
    }

    //==================================================================
    function set_folder_parent($parent)
    {
        global    $modx;
        if ($parent != 0) {
            return $modx->db->update(
                array('isfolder' => 1),
                $this->tbl_content,
                'id=' . $parent
            );
        }
        return TRUE;
    }

    //==================================================================
    // 確認表示フィールド名の初期化
    function init_verify_fields()
    {
        $verify_fieldnames = trim($this->paramV('verify_fieldnames'));
        if ($verify_fieldnames) {
            $this->verify_fieldnames = explode(',', $verify_fieldnames);
        } else {
            $this->verify_fieldnames = $this->csv->fieldnames;
        }
        $this->verify_cols = min(
            $this->paramV('num_verify_cols'),
            count($this->verify_fieldnames)
        );
    }

    //==================================================================
    // 必須フィールド名の初期化
    function init_require_fieldnames()
    {
        $this->require_fieldnames = array();
        if ($this->paramV('require_fieldnames')) {
            $this->require_fieldnames = array_map('trim', explode(',', $this->paramV('require_fieldnames')));
            foreach ($this->require_fieldnames as $fn) {
                if (! in_array($fn, $this->csv->fieldnames)) {
                    putMsg("MSG_WARNING", '必須フィールド(' . $fn . ')はCSVファイルにありません。');
                }
            }
        }
    }

    //==================================================================
    function matching()
    {
        global    $modx;
        //---- 更新用一致判定フィールドのデータ取得
        $matching_fn = $this->paramV('matching_fieldname');
        if (! empty($matching_fn)) {
            if (isset($this->docvars_fidx[$matching_fn])) {
                $sql = $this->make_sql_matching_docvars($matching_fn);
                if ($drs = $modx->db->query($sql)) {
                    $this->matching_values = array();
                    while ($row = $modx->db->getRow($drs)) {
                        $this->matching_values[$row[$matching_fn]][] = $row['id'];
                    }
                    $this->matching_table = 'docvars';
                } else {
                    putMsg("MSG_ERROR", 'データベースエラー' . __LINE__);
                }
            } else if (isset($this->tmplvars_id[$matching_fn])) {
                $tmplvarid = $this->tmplvars_id[$matching_fn];
                $sql = $this->make_sql_matching_tmplvars($tmplvarid);
                if ($drs = $modx->db->query($sql)) {
                    $this->matching_values = array();
                    while ($row = $modx->db->getRow($drs)) {
                        $this->matching_values[$row['value']][] = $row['contentid'];
                    }
                    $this->matching_table = 'tmplvars';
                    $this->matching_tmplvarid = $tmplvarid;
                } else {
                    putMsg("MSG_ERROR", 'データベースエラー' . __LINE__);
                }
            } else {
                putMsg("MSG_WARNING", '更新用一致判定フィールド(' . $matching_fn . ')はMODxで使用されていません。');
            }
        }
    }

    //==================================================================
    function make_sql_matching_docvars($matching_fn)
    {
        global    $modx;
        $sql = "SELECT id, " . $modx->db->escape($matching_fn) . " FROM " . $this->tbl_content;
        $sql .= " WHERE deleted=0";
        if (! in_array('parent', $this->csv->fieldnames) && empty($this->bynames['parent'])) {
            $sql .= " AND parent=" . $this->paramV('doc_parent');
        }
        return $sql;
    }

    //==================================================================
    function make_sql_matching_tmplvars($tmplvarid)
    {
        $sql = "SELECT DISTINCT tvc.contentid, tvc.value FROM " . $this->tbl_tmplvar_contvals . " tvc";
        $sql .= " LEFT JOIN " . $this->tbl_content . " sc ON tvc.contentid=sc.id";
        $sql .= " WHERE tvc.tmplvarid=" . $tmplvarid . " AND sc.deleted=0";
        if (! in_array('parent', $this->csv->fieldnames) && empty($this->bynames['parent'])) {
            $sql .= " AND sc.parent=" . $this->paramV('doc_parent');
        }
        return $sql;
    }

    //==================================================================
    function getbrothers()
    {
        global    $modx;

        $this->matched_documents = array();
        $this->brothers = array();
        $fieldnames = $this->csv->fieldnames;
        if (! in_array('parent', $fieldnames) && empty($this->bynames['parent'])) {
            $sql = $this->make_sql_getbrothers();
            if ($drs = $modx->db->query($sql)) {
                while ($row = $modx->db->getRow($drs)) {
                    $this->brothers[$row['id']] = $row;
                }
            } else {
                putMsg("MSG_ERROR", 'データベースエラー' . __LINE__);
            }
        }
    }

    //==================================================================
    function make_sql_getbrothers()
    {
        $sql = "SELECT id, pagetitle, published FROM " . $this->tbl_content;
        $sql .= " WHERE deleted=0 AND parent=" . $this->paramV('doc_parent');
        return $sql;
    }

    //##################################################################
    // データ処理
    //##################################################################

    //==================================================================
    function init_records_gen($gen_cols = '')
    {
        $gen_name = $this->paramV('records_gen_class');
        $gen_param = $this->paramV('records_gen_param');
        if ($gen_cols === '') {
            $gen_cols = $this->paramV('records_gen_cols');
        }
        if ($gen_cols === '') {
            $gen_cols = $this->verify_cols + 2;
        }
        $this->records_gen = new $gen_name($gen_cols, $gen_param);
    }

    //==================================================================
    function loaddata()
    {
        $this->tag_gen_record_head_title();
        $this->tag_gen_record_field_title();
        $this->records_gen->nextRow();

        //---- データレコードの処理
        $this->rec_no = 0;
        while (($this->rec = $this->csv->read_rec()) !== FALSE) {
            $this->rec_no++;
            $this->init_result();
            if (! $this->check_empty_record()) {
                $this->ids = $this->check_exists_rec();
                if ($this->ids !== FALSE) {
                    $this->unset_matching_brothers();
                }
                $this->parent = FALSE;
                $this->check_save_record();
                if ($this->parent) {
                    $this->result['status'] .= '(parent=' . $this->parent . ')';
                }
            }
            $this->tag_gen_record_head_data();
            $this->tag_gen_record_field_data();
            $this->records_gen->nextRow();
        }
        $conf_tags = '';
        $conf_tags .= $this->section_header('データレコード一覧');
        $conf_tags .= $this->records_gen->get();
        $conf_tags .= $this->section_footer();
        $this->conf_records_tags = $conf_tags;

        if ($this->act == 'save') {
            // 親ドキュメントの更新
            putMsg("MSG_OPERATION", 'データを登録しました。');
            $this->completed = TRUE;
        }
    }

    //==================================================================
    function init_result()
    {
        $this->result = array('status' => '', 'color' => '');
    }
    function tag_gen_record_head_title()
    {
        $gen = &$this->records_gen->col('No.');
        $gen->col('id/status');
        unset($gen);
    }
    function tag_gen_record_head_data()
    {
        $this->records_gen->col($this->rec_no, '', 'align="right"');
        $this->records_gen->col($this->result['status'], $this->result['color']);
    }

    //==================================================================
    function tag_gen_record_field_title()
    {
        $varifynames = $this->verify_fieldnames;
        for ($k = 0; $k < $this->verify_cols; $k++) {
            $this->tag_gen_record_field_title_col($varifynames[$k]);
        }
    }
    function tag_gen_record_field_title_col($fn)
    {
        $this->records_gen->sCol($fn);
    }
    function tag_gen_record_field_data()
    {
        if (count($this->rec) == 0) {
            return;
        }
        $varifynames = $this->verify_fieldnames;
        for ($k = 0; $k < $this->verify_cols; $k++) {
            $fn = $varifynames[$k];
            $this->tag_gen_record_field_data_col($fn);
        }
    }
    function tag_gen_record_field_data_col($fn)
    {
        $this->records_gen->col($this->rec[$fn]);
    }

    //==================================================================
    function check_empty_record()
    {
        if (count($this->rec) == 0) {
            $this->result['status'] = '(空行)';
            return TRUE;
        }
        $effective_fields = array_filter($this->rec, array(&$this, "check_field_filter"));
        if (count($effective_fields) == 0) {
            $this->result['status'] = '(空行)';
            return TRUE;
        }
        foreach ($this->require_fieldnames as $fn) {
            if ($this->rec[$fn] === '') {
                $this->result['status'] = '(不完全)';
                $this->result['color'] = 'orange';
                return TRUE;
            }
        }
        return FALSE;
    }
    function check_field_filter($value)
    {
        return ($value !== '');
    }

    //==================================================================
    function check_exists_rec()
    {
        if (! empty($this->matching_values)) {
            $matching_fn = $this->paramV('matching_fieldname');
            $fidx = array_search($matching_fn, $this->csv->fieldnames);
            if ($fidx !== FALSE) {
                $val = $this->rec[$matching_fn];
                if (array_key_exists($val, $this->matching_values)) {
                    if (count($this->matching_values[$val]) > 1) {
                        $this->curErr = '更新用一致判定フィールドに該当ドキュメントが複数存在';
                        $this->curErrLevel = "MSG_WARNING";
                        $this->curErrParam[$matching_fn] = $val;
                    }
                    return $this->matching_values[$val];
                }
            }
        }
        return FALSE;
    }

    //==================================================================
    function unset_matching_brothers()
    {
        foreach ($this->ids as $id) {
            if (array_key_exists($id, $this->brothers)) {
                $this->matched_documents[$id] = $this->brothers[$id];
                unset($this->brothers[$id]);
            }
        }
    }

    //==================================================================
    function check_save_record()
    {
        if (! $this->search_parent()) {
        } else if ($this->check_rec_perm() === FALSE) {
            $this->result['status'] = $this->make_rowmsg();
            $this->result['color'] = 'red';
            if (! $this->curErrLevel) {
                $this->curErrLevel = "MSG_ERROR";
            }
            putMsg($this->curErrLevel, $this->make_errmsg());
        } else if (! $this->check_record()) {
        } else if ($this->act == 'save') {
            $status = $this->save_record();
            if ($status) {
                $this->result['status'] = $status;
            } else {
                $this->result['status'] = $this->make_rowmsg();
                $this->result['color'] = 'red';
            }
        } else {
            if ($this->ids === FALSE) {
                $this->result['status'] = 'New';
                $this->result['color'] = 'red';
            } else {
                $this->result['status'] = implode(',', $this->ids);
            }
        }
    }

    //==================================================================
    function search_parent()
    {
        global    $modx;
        $parent_alias_fn = $this->paramV('parent_alias_fieldname');
        if ($parent_alias_fn == '') {
            return TRUE;
        }
        $doc_parent = $this->paramV('doc_parent');
        if ($doc_parent == '') {
            $this->curErr = '親ドキュメントIDの指定無し';
            return FALSE;
        }
        $parent_alias = $this->rec[$parent_alias_fn];
        if ($parent_alias == '') {
            $this->curErr = '親ドキュメントのエイリアス指定無し';
            $this->curErrParam['parent_alias_fieldname'] = $parent_alias_fn;
            return FALSE;
        }
        $sql = "SELECT id FROM " . $this->tbl_content;
        $sql .= " WHERE deleted=0 AND parent=" . $doc_parent;
        $sql .= " AND alias='" . $modx->db->escape($parent_alias) . "'";
        if (! $drs = $modx->db->query($sql)) {
            putMsg("MSG_ERROR", 'データベースエラー' . __LINE__);
            return FALSE;
        }
        $parents = $modx->db->getColumn('id', $drs);
        if (count($parents) == 0) {
            $this->curErr = '該当親ドキュメント無し';
            $this->curErrParam['alias'] = $parent_alias;
            return FALSE;
        }
        if (count($parents) > 1) {
            $this->curErr = '該当親ドキュメントが複数';
            $this->curErrParam['alias'] = $parent_alias;
            $this->curErrParam['ids'] = implode(',', $parents);
            return FALSE;
        }
        $this->parent = $parents[0];
        return $this->check_parent_perm($this->parent);
    }

    //==================================================================
    function check_record()
    {
        return TRUE;
    }

    //==================================================================
    function check_rec_perm()
    {
        $status = FALSE;
        $this->clear_err();
        //---- 親ドキュメントへのアクセス権限のチェック
        if (! $this->check_parent_field()) {
            $this->curErr = '親ドキュメントへのアクセス権限無し';
        } else {
            if ($this->ids === FALSE && ! $this->permNewDocument) {
                $this->curErr = 'ドキュメントの作成権限無し';
            } else if ($this->ids !== FALSE && ! $this->permEditDocument) {
                $this->curErr = 'ドキュメントの編集権限無し';
            } else if ($this->ids !== FALSE && ! checkUserDocPerm($this->ids)) {
                $this->curErr = 'ドキュメントのアクセス権限無し';
                $this->curErrParam['id'] = implode(',', $this->ids);
            } else {
                $status = TRUE;
            }
        }
        return $status;
    }

    //==================================================================
    function save_record()
    {
        $status = FALSE;
        if ($this->ids === FALSE) {
            //---- ドキュメントの新規作成
            if ($docid = $this->insert_csv_document()) {
                //---- テンプレート変数の登録
                if ($this->insert_csv_tmplvar_contvals($docid)) {
                    $status = $docid;
                }
                $this->docid = $docid;
            }
        } else {
            //---- ドキュメントの更新
            if ($this->update_csv_document()) {
                //---- テンプレート変数の登録
                if ($this->update_csv_tmplvar_contvals_each()) {
                    $status = implode(',', $this->ids);
                }
            }
        }
        return $status;
    }

    //==================================================================
    function clear_err()
    {
        $this->curErr = '';
        $this->curErrLevel = '';
        $this->curErrParam = array();
    }
    function make_errmsg()
    {
        $mstr = $this->make_rowmsg();
        if (! empty($this->rec_no)) {
            $mstr .= 'rec_no';
            $mstr .= '(' . $this->rec_no . ')';
        }
        return $mstr;
    }
    function make_rowmsg()
    {
        $mstr = $this->curErr;
        foreach ($this->curErrParam as $name => $val) {
            $mstr .= ':' . $name . '=[' . $val . ']';
        }
        return $mstr;
    }

    //==================================================================
    //	check_parent_field
    //	親ドキュメントがCSVに含まれている場合、アクセス権限のチェック
    //==================================================================
    function check_parent_field()
    {
        $fn = 'parent';
        if (! empty($this->bynames['parent'])) {
            $fn = $this->bynames['parent'];
        }
        $fidx = array_search($fn, $this->csv->fieldnames);
        if ($fidx !== FALSE) {
            $parent = $this->rec[$fidx];
            return $this->check_parent_perm($parent);
        }
        return TRUE;
    }

    //==================================================================
    //	insert_csv_document
    //	CSVファイルの内容で、ドキュメントデータベースにレコードを追加する。
    //==================================================================
    function insert_csv_document()
    {
        global    $modx;
        $fields = $this->make_insert_document_rec();
        if (! $docid = $modx->db->insert($fields, $this->tbl_content)) {
            $this->curErr = 'ドキュメント作成でエラー発生';
            $this->curErrParam['error'] = $modx->db->getLastError();
        }
        return $docid;
    }
    function make_insert_document_rec()
    {
        $fields = array_merge($this->default_doc, $this->default_insert);
        $fields = $this->make_document_rec($fields);
        $fields = $this->make_insert_document_published($fields);
        return $fields;
    }
    function make_insert_document_published($fields)
    {
        if ($fields['published']) {
            // 公開設定日、公開設定者を記録
            $fields = array_merge($fields, $this->default_publish);
            if (empty($fields['pub_date']) && $this->paramV('set_pub_date')) {
                // 公開日時に現在時刻を設定する
                $fields['pub_date'] = $this->now;
            }
        }
        return $fields;
    }

    //==================================================================
    //	update_csv_document
    //	CSVファイルの内容で、ドキュメントデータベースのレコードを更新する。
    //==================================================================
    function update_csv_document()
    {
        global    $modx;
        $fields = $this->make_update_document_rec();
        $where = 'id in (' . implode(',', $this->ids) . ')';
        if (! $ret = $modx->db->update($fields, $this->tbl_content, $where)) {
            $this->curErr = 'ドキュメント更新でエラー発生';
            $this->curErrParam['id'] = implode(',', $this->ids);
            $this->curErrParam['error'] = $modx->db->getLastError();
        }
        return $ret;
    }
    function make_update_document_rec()
    {
        $fields = $this->default_doc;
        $fields = array_merge($this->default_doc, $this->default_update);
        $fields = $this->make_document_rec($fields);
        $fields = $this->make_update_document_published($fields, $this->ids[0]);
        return $fields;
    }
    function make_update_document_published($fields, $id)
    {
        $doc = $this->matched_documents[$id];
        if (! $doc['published'] && $fields['published']) {
            // 公開設定日、公開設定者を記録
            $fields = array_merge($fields, $this->default_publish);
        }
        return $fields;
    }

    //==================================================================
    function make_document_rec($fields = NULL)
    {
        if (is_null($fields)) {
            $fields = $this->default_doc;
        }
        foreach (array_keys($this->docvars_fidx) as $fn) {
            $fields[$fn] = $this->make_document_field($fn, $this->rec[$fn]);
        }
        foreach ($this->bynames as $target_fn => $fn) {
            $fields[$target_fn] = $this->make_document_field($target_fn, $this->rec[$fn]);
        }
        if ($this->parent) {
            $fields['parent'] = $this->parent;
        }
        return $fields;
    }

    //==================================================================
    function make_document_field($fn, $value)
    {
        global    $modx;
        if ($fn == 'pagetitle') {
            // pagetitleに改行があるとツリーで不具合が起こる
            $value = str_replace(array("\n", "\r"), "", $value);
        } else {
            $value = $this->nl2br_value($fn, $value);
        }
        return $modx->db->escape($value);
    }

    //==================================================================
    function nl2br_value($fn, $value)
    {
        if (! $this->nl2br) {
            return $value;
        }
        if ($this->nl2br_fieldnames && ! in_array($fn, $this->nl2br_fieldnames)) {
            return $value;
        }
        return nl2br($value);
    }

    //==================================================================
    //	insert_csv_tmplvar_contentvalues
    //	CSVファイルの内容で、テンプレート変数データベースに値を登録する。
    //==================================================================
    function insert_csv_tmplvar_contvals($docid)
    {
        $ret = TRUE;
        foreach (array_keys($this->tmplvars_fidx) as $tmplvarid) {
            $fn = $this->csv->fieldnames[$this->tmplvars_fidx[$tmplvarid]];
            if ($this->rec[$fn] === '') {
                continue;
            }
            $value = $this->make_tmplvar_field($fn, $this->rec[$fn]);
            $fields = $this->make_tmplvar_contvals_rec($docid, $tmplvarid, $value);
            if (! $this->insert_tmplvar_contvals($fields)) {
                $ret = FALSE;
            }
        }
        return $ret;
    }

    //==================================================================
    function insert_tmplvar_contvals($fields)
    {
        global    $modx;
        if (! $modx->db->insert($fields, $this->tbl_tmplvar_contvals)) {
            $this->curErr = 'テンプレート変数作成でエラー発生';
            $this->curErrParam['tmplvarid'] = $fields['tmplvarid'];
            $this->curErrParam['contentid'] = $fields['contentid'];
            $this->curErrParam['error'] = $modx->db->getLastError();
            return FALSE;
        }
        return TRUE;
    }

    //==================================================================
    //	update_csv_tmplvar_contvals_each
    //	CSVファイルの内容で、テンプレート変数データベースに値を登録する。
    //==================================================================
    function update_csv_tmplvar_contvals_each()
    {
        $ret = TRUE;
        foreach ($this->ids as $docid) {
            if (! $this->update_csv_tmplvar_contvals($docid)) {
                $ret = FALSE;
            }
        }
        return $ret;
    }

    //==================================================================
    function update_csv_tmplvar_contvals($docid)
    {
        $ret = TRUE;
        foreach ($this->tmplvars_fnames as $fn) {
            $tmplvarid = $this->tmplvars_id[$fn];
            if ($this->rec[$fn] === '') {
                if (! $this->delete_tmplvar_contvals($docid, $tmplvarid)) {
                    $ret = FALSE;
                }
            } else if (! $this->update_insert_tmplvar_contvals($docid, $tmplvarid, $this->rec[$fn], $fn)) {
                $ret = FALSE;
            }
        }
        return $ret;
    }

    function delete_tmplvar_contvals($contentid, $tmplvarid)
    {
        global    $modx;
        $where = 'tmplvarid=' . $tmplvarid . ' AND contentid=' . $contentid;
        if (! $ret = $modx->db->delete($this->tbl_tmplvar_contvals, $where)) {
            $this->curErr = 'テンプレート変数削除でエラー発生';
            $this->curErrParam['tmplvarid'] = $tmplvarid;
            $this->curErrParam['contentid'] = $contentid;
            $this->curErrParam['error'] = $modx->db->getLastError();
        }
        return $ret;
    }

    //==================================================================
    function update_insert_tmplvar_contvals($docid, $tmplvarid, $fvalue, $fn)
    {
        global    $modx;
        $ret = TRUE;
        $value = $this->make_tmplvar_field($fn, $fvalue);
        $fields = $this->make_tmplvar_contvals_rec($docid, $tmplvarid, $value);
        $where = 'tmplvarid=' . $fields['tmplvarid'] . ' AND contentid=' . $fields['contentid'];
        $sql = $this->make_sql_select_tmplvars($where);
        if ($drs = $modx->db->query($sql)) {
            $cnt = $modx->db->getValue($drs);
            if ($cnt > 0) {
                if (! $this->update_tmplvar_contvals($fields, $where)) {
                    $ret = FALSE;
                }
            } else {
                if (! $this->insert_tmplvar_contvals($fields)) {
                    $ret = FALSE;
                }
            }
            return $ret;
        }
        putMsg("MSG_ERROR", 'データベースエラー' . __LINE__);
        return FALSE;
    }

    //==================================================================
    function make_sql_select_tmplvars($where)
    {
        $sql = "SELECT COUNT(*) FROM " . $this->tbl_tmplvar_contvals;
        $sql .= " WHERE " . $where;
        return $sql;
    }

    //==================================================================
    function update_tmplvar_contvals($fields, $where)
    {
        global    $modx;
        if (! $ret = $modx->db->update($fields, $this->tbl_tmplvar_contvals, $where)) {
            $this->curErr = 'テンプレート変数更新でエラー発生';
            $this->curErrParam['tmplvarid'] = $fields['tmplvarid'];
            $this->curErrParam['contentid'] = $fields['contentid'];
            $this->curErrParam['error'] = $modx->db->getLastError();
        }
        return $ret;
    }

    //==================================================================
    function make_tmplvar_contvals_rec($docid, $tmplvarid, $value)
    {
        $fields = array(
            'tmplvarid'    =>    $tmplvarid,
            'contentid'    =>    $docid,
            'value'        =>    $value,
        );
        return $fields;
    }

    //==================================================================
    function make_tmplvar_field($fn, $value)
    {
        global    $modx;
        $value = $this->nl2br_value($fn, $value);
        return $modx->db->escape($value);
    }

    //==================================================================
    function init_brothers_gen($gen_cols = '')
    {
        $gen_name = $this->paramV('brothers_gen_class');
        $gen_param = $this->paramV('brothers_gen_param');
        if ($gen_cols === '') {
            $gen_cols = $this->paramV('brothers_gen_cols');
        }
        if ($gen_cols === '') {
            $gen_cols = 2;
        }
        $this->brothers_gen = new $gen_name($gen_cols, $gen_param);
    }

    //==================================================================
    function deletebrothers()
    {
        $this->tag_gen_brothers_title();
        $this->brothers_gen->nextRow();

        foreach ($this->brothers as $brother) {
            $this->delete_brother($brother);
            $this->brothers_gen->nextRow();
        }

        $conf_tags = '';
        $conf_tags .= $this->section_header('削除レコード一覧');
        $conf_tags .= $this->brothers_gen->get();
        $conf_tags .= $this->section_footer();
        $this->conf_brothers_tags = $conf_tags;

        if ($this->act == 'save') {
            $this->completed = TRUE;
        }
    }
    function tag_gen_brothers_title()
    {
        $gen = &$this->brothers_gen->sCol('id');
        $gen->sCol('pagetitle');
        unset($gen);
    }

    //==================================================================
    function delete_brother($brother)
    {
        global    $modx;
        if ($this->act == 'save') {
            $fields = $this->default_delete;
            $where = 'id=' . $brother['id'];
            if (! $ret = $modx->db->update($fields, $this->tbl_content, $where)) {
                $this->curErr = 'ドキュメント更新でエラー発生';
                $this->curErrParam['id'] = $brother['id'];
                $this->curErrParam['error'] = $modx->db->getLastError();
                $gen = &$this->brothers_gen->col($this->make_rowmsg(), "red");
                $gen->col($brother['pagetitle']);
                unset($gen);
                return;
            }
        }
        $gen = &$this->brothers_gen->col($brother['id']);
        $gen->col($brother['pagetitle']);
        unset($gen);
    }

    //==================================================================
    function init_form_gen($gen_cols = '')
    {
        $gen_name = $this->paramV('form_gen_class');
        $gen_param = $this->paramV('form_gen_param');
        if ($gen_cols === '') {
            $gen_cols = $this->paramV('form_gen_cols');
        }
        if ($gen_cols === '') {
            $gen_cols = 2;
        }
        $this->form_gen = new $gen_name($gen_cols, $gen_param);
    }

    //==================================================================
    // make form tags
    function makeform()
    {
        global $c2d_error;    // Error flag

        $this->runparams = explode(',', $this->paramV('runparams'));
        foreach ($this->runparams as $name) {
            $p = &$this->param($name);
            $this->form_gen->sCol($p->getTitle());
            $this->form_gen->hCol($p->inputTag($name));
            $this->form_gen->nextRow();
            unset($p);
        }
        $submit_tags = '<input name="confirm" value="確認する" type="submit" />　';
        if ($this->act == 'confirm' && ! $c2d_error) {
            $submit_tags .= '<input name="save" value="登録する" type="submit" />';
        }
        $this->form_gen->hCol($submit_tags, NULL, 2);

        $form_tags = '';
        $form_tags .= $this->section_header('parameters');
        $form_tags .= '
			「' . $this->paramV('csv_dname') . '」にアップロードしてあるCSVファイルを使います。
			<form action="" method="post">
		';
        $form_tags .= $this->form_gen->get();
        $form_tags .= '</form>';
        $form_tags .= $this->section_footer();
        $this->form_tags = $form_tags;
    }

    //==================================================================
    function section_header($title)
    {
        global $modx;
        $theme_path = $modx->config('base_path') . 'manager/media/style/' . $modx->config["manager_theme"] . '/';
        $tags  = '<style type=text/css>' . "\n";
        $tags .= 'input.inputBox {';
        $tags .= 'background: #fff url(' . $theme_path . 'images/misc/input-bg.gif) repeat-x top left;';
        $tags .= '}' . "\n";
        $tags .= 'table {';
        $tags .= 'border-collapse:collapse;border:none;';
        $tags .= '}' . "\n";
        $tags .= 'table td {';
        $tags .= 'border:1px solid#ccc;padding:4px;';
        $tags .= '}' . "\n";
        $tags .= '</style>' . "\n";

        $tags .= '<div class="section">';
        $tags .= '<div class="sectionHeader">' . $title . '</div>';
        $tags .= '<div class="sectionBody">' . "\n";
        return $tags;
    }
    function section_footer()
    {
        $tags = "</div>\n";
        $tags = "</div>\n";
        return $tags;
    }

    //==================================================================
    // output display
    function output()
    {
        global    $c2d_msg_tags;
        echo '<h1>' . $this->paramV('my_name') . '</h1>' . "\n";
        if (count($c2d_msg_tags) > 0) {
            echo $this->section_header('messages');
            echo implode("<br />\n", $c2d_msg_tags);
            echo $this->section_footer();
        }
        echo $this->form_tags;
        echo $this->conf_brothers_tags;
        echo $this->conf_fields_tags;
        echo $this->conf_records_tags;
    }

    //==================================================================
    // reload display
    function reload()
    {
        header("Location: index.php?r=1&a=7");
    }
}

/***********************************************************************
	Class for CSV file
 ***********************************************************************/
class Csv
{
    var    $csv_path;
    var    $params;
    var    $fieldnames;    // フィールド名配列
    var    $skiped_lines;    // 読み飛ばし行
    var    $fp;            // CSV、もしくは、一時ファイルのファイルポインタ

    //==================================================================
    // Constructor
    function __construct(&$params)
    {
        $this->params = &$params;
        $this->setLocale();
    }
    //==================================================================
    function setLocale()
    {
        if (! setlocale(LC_ALL, $this->paramV('sys_locale'))) {
            putMsg("MSG_ERROR", 'setlocale error(' . $this->paramV('sys_locale') . ')');
            return;
        }
    }

    //==================================================================
    // get parameter
    function paramV($name)
    {
        $p = &$this->params->element($name);
        $ret = $p->getVal();
        unset($p);
        return $ret;
    }
    //==================================================================
    function &param($name)
    {
        return $this->params->element($name);
    }

    //==================================================================
    // Open or Convert + make temporary file
    function open(&$csv_path)
    {
        global    $modx;
        if (empty($csv_path)) {
            // CSVファイルパスの生成
            $csv_dname = $this->paramV('csv_dname');
            $csv_fname = $this->paramV('csv_fname');
            $csv_path = $modx->config['base_path'] . '/' . $csv_dname . $csv_fname;
            $this->csv_path = $csv_path;
        } else {
            $this->csv_path = $csv_path;
        }
        //---- initialize this object
        $this->fieldnames = array();
        $this->skiped_lines = array();
        $this->fp = FALSE;

        $sys_encoding = $this->paramV('sys_encoding');
        $csv_encoding = $this->paramV('csv_encoding');

        //---- no need convert
        if ($sys_encoding == $csv_encoding || $csv_encoding == 'ASCII') {
            if (! $this->fp = fopen($this->csv_path, "r")) {
                putMsg("MSG_ERROR", 'CSVファイルオープンエラー(' . $this->csv_path . ')');
                return FALSE;
            }
            return TRUE;
        }
        //---- need convert & make temporary file
        if (! extension_loaded('mbstring')) {
            putMsg("MSG_ERROR", 'mbstringモジュールが使用不可です。');
            return FALSE;
        }
        if (! $csv = file_get_contents($this->csv_path)) {
            putMsg("MSG_ERROR", 'CSVファイル読み込みエラー(' . $this->csv_path . ')');
            return FALSE;
        }
        $encoded_csv = mb_convert_encoding($csv, $sys_encoding, $csv_encoding);
        if (! $this->fp = tmpfile()) {
            putMsg("MSG_ERROR", '一時ファイルオープンエラー');
            return FALSE;
        }
        if (fwrite($this->fp, $encoded_csv) === FALSE) {
            putMsg("MSG_ERROR", '一時ファイル書き込みエラー');
            fclose($this->fp);
            return FALSE;
        }
        rewind($this->fp);
        return TRUE;
    }

    //==================================================================
    // load csv header
    function load_header()
    {
        $csv_only_data = $this->paramV('csv_only_data');
        if ($csv_only_data) {
            $fieldnames = $this->paramV('csv_fieldnames');
            if ($fieldnames == '') {
                putMsg("MSG_ERROR", 'フィールド名が指定されていません。');
                return FALSE;
            }
            $this->fieldnames = explode(',', $fieldnames);
        } else {
            $maxreclen = $this->paramV('csv_maxreclen');
            $delimiter = $this->paramV('csv_delimiter');
            $enclosure = $this->paramV('csv_enclosure');
            //---- フィールド名読み込み
            if (! $this->fieldnames = fgetcsv($this->fp, $maxreclen, $delimiter, $enclosure)) {
                putMsg("MSG_ERROR", 'CSVファイル読み込みエラー(' . $this->csv_path . ')');
                return FALSE;
            }
            //---- 行のスキップ
            $this->skiped_lines = array();
            for ($j = 0; $j < $this->paramV('csv_skiplines'); $j++) {
                if (! $this->skiped_lines[] = fgetcsv($this->fp, $maxreclen, $delimiter, $enclosure)) {
                    putMsg("MSG_ERROR", 'CSVファイル読み込みエラー(' . $this->csv_path . ')');
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    //==================================================================
    // read csv record
    function read_rec($null_line_skip = FALSE)
    {
        $maxreclen = $this->paramV('csv_maxreclen');
        $delimiter = $this->paramV('csv_delimiter');
        $enclosure = $this->paramV('csv_enclosure');
        do {
            //---- 読み込み
            if (! $row = fgetcsv($this->fp, $maxreclen, $delimiter, $enclosure)) {
                if (! feof($this->fp)) {
                    putMsg("MSG_ERROR", 'CSVファイル読み込みエラー(' . $this->csv_path . ')');
                }
                return FALSE;
            }
        } while (is_null($row[0]) && $null_line_skip);    // 空行スキップ
        $rec = array();
        if (! is_null($row[0])) {
            for ($i = 0; $i < count($this->fieldnames); $i++) {
                if ($this->fieldnames[$i] != '') {
                    $rec[$this->fieldnames[$i]] = $row[$i];
                }
            }
        }
        return $rec;
    }

    //==================================================================
    // close
    function close()
    {
        fclose($this->fp);
        $this->fieldnames = array();
        $this->skiped_lines = array();
        $this->fp = FALSE;
    }
}



/***********************************************************************
	Check user documents permissions
 ***********************************************************************/
function checkUserDocPerm($docids)
{
    global    $modx;

    $perm_class_php = "manager/processors/user_documents_permissions.class.php";
    require_once $modx->config['base_path'] . $perm_class_php;
    if (! is_array($docids)) {
        $docids = array($docids);
    }
    $ret = TRUE;
    if ($modx->config['use_udperms'] == 1) {
        $udperms = new udperms();
        $udperms->user = $modx->getLoginUserID();
        $udperms->role = $_SESSION['mgrRole'];
        foreach ($docids as $docid) {
            $udperms->document = $docid;
            if (! $udperms->checkPermissions()) {
                $ret = FALSE;
            }
        }
    }
    return $ret;
}

/***********************************************************************
	Messaging definition
 ***********************************************************************/
function putMsg($mlevel, $mstr)
{
    global    $e, $c2d_msg_tags, $c2d_error;
    switch ($mlevel) {
        case "MSG_ERROR":
            $c2d_msg_tags[] = '<font color="red"><strong>' . $mstr . '</strong></font>';
            $c2d_error = TRUE;
            break;
        case "MSG_WARNING":
            $c2d_msg_tags[] = '<font color="red">' . $mstr . '</font>';
            break;
        case "MSG_OPERATION":
            $c2d_msg_tags[] = '<strong>' . $mstr . '</strong>';
            break;
    }
}

function &get_templates_hash()
{
    global    $modx;
    $tbl_site_templates = $modx->getFullTableName('site_templates');
    $drs = $modx->db->select('id,templatename', $tbl_site_templates);
    $templates = $modx->db->makeArray($drs);
    $templates_hash = make_hash($templates, 'templatename', 'id');
    return $templates_hash;
}

// ハッシュレコードの配列から、2フィールドを使って、1つのハッシュを作る。
function &make_hash($recs, $key_field, $value_field)
{
    $hash = array();
    foreach ($recs as $rec) {
        $hash[$rec[$key_field]] = $rec[$value_field];
    }
    return $hash;
}

/***********************************************************************
	Encoding names for mbstring
 ***********************************************************************/
function &mb_encode_list()
{
    if (version_compare(phpversion(), '5.0.0') >= 0) {
        return mb_list_encodings();
    }
    return array(
        "UCS-4",
        "UCS-4BE",
        "UCS-4LE",
        "UCS-2",
        "UCS-2BE",
        "UCS-2LE",
        "UTF-32",
        "UTF-32BE",
        "UTF-32LE",
        "UTF-16",
        "UTF-16BE",
        "UTF-16LE",
        "UTF-7",
        "UTF7-IMAP",
        "UTF-8",
        "ASCII",
        "EUC-JP",
        "SJIS",
        "eucJP-win",
        "SJIS-win",
        "ISO-2022-JP",
        "JIS",
        "ISO-8859-1",
        "ISO-8859-2",
        "ISO-8859-3",
        "ISO-8859-4",
        "ISO-8859-5",
        "ISO-8859-6",
        "ISO-8859-7",
        "ISO-8859-8",
        "ISO-8859-9",
        "ISO-8859-10",
        "ISO-8859-13",
        "ISO-8859-14",
        "ISO-8859-15",
        "byte2be",
        "byte2le",
        "byte4be",
        "byte4le",
        "BASE64",
        "HTML-ENTITIES",
        "7bit",
        "8bit",
        "EUC-CN",
        "CP936",
        "HZ",
        "EUC-TW",
        "CP950",
        "BIG-5",
        "EUC-KR",
        "UHC",
        "ISO-2022-KR",
        "Windows-1251",
        "Windows-1252",
        "CP866",
        "KOI8-R",
    );
}

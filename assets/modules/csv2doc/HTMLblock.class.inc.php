<?php
/**
 * Csv2Doc MODx module - HTML block generator
 *
 * @package Csv2Doc
 * @author Kazuyuki Ikeda (HIKIDAS Co.,Ltd)	
 * @link http://www.hikidas.com/
 * @version 0.9.2
 */

/***********************************************************************
	Dependence:
		Method 'iniCharset' use MODx '$modx_charset'
***********************************************************************/

/*======================================================================
	class HTMLblock
	Basic class for Generator of HTML block (Simple rows using '<br />')
------------------------------------------------------------------------
	Usage example:
		$tag_gen = new HTMLblock();
		$tag_gen->col("This is the 1st row.")->nextRow();
		$tag_gen->col("This is the 2nd row.")->nextRow();
		  :
		echo $tag_gen->get();
------------------------------------------------------------------------
	Usual methods:
		col		put a column with htmlspecialchars() & nl2br()
		sCol	put a strong column with htmlspecialchars() & nl2br()
		eCol	put an empty column
		hCol	put a html tags column
		nCol	put a column with htmlspecialchars() (without nl2br())
		nextRow	end the current row (& prepare for the next row)
		get		get the generated tags
======================================================================*/
class HTMLblock {
	//---- properties for setting
	var $charset;			// encoding charcter-set
	var	$num_cols;			// number of columns (case 0: columns free)
	var	$begin_block_tags;	// beginning tags of block
	var	$end_block_tags;	// ending tags of block
	var	$begin_row_tags;	// beginning tags of each row
	var	$end_row_tags;		// ending tags of each row
	var	$begin_col_tags;	// beginning tags of each column
	var	$end_col_tags;		// ending tags of each column
	var $empty_col_tags;	// tags for fill the empty column
	//---- properties for working
	var	$tags;				// generated tags (rows buffer string)
	var	$cnt_cols;			// columns counter
	var	$cols_buffer;		// columns buffer array

	/*******************************************************************
		constructor & initializers
	*******************************************************************/
	// constructor
	function __construct($num_cols=0, $block_attrs=NULL, $row_attrs=NULL, $col_attrs=NULL, $charset=NULL) {
		$this->initCharset($charset);
		$this->initSettings($num_cols, $block_attrs, $row_attrs, $col_attrs);
		$this->initBlock();
	}
	// initialize the character-set
	function initCharset($charset=NULL) {
		if (! empty($charset)) {
			$this->charset = $charset;
		} else if (! empty($_GLOBALS['modx_charset'])) {
			$this->charset = $_GLOBALS['modx_charset'];
		} else {
			$this->charset = 'UTF-8';
		}
		return $this;
	}
	// initialize the properties for basic block
	function initSettings($num_cols, $block_attrs=NULL, $row_attrs=NULL, $col_attrs=NULL) {
		$this->num_cols = $num_cols;
		$this->begin_block_tags = "";
		$this->end_block_tags = "";
		$this->begin_row_tags = "";
		$this->end_row_tags = "<br />\n";
		$this->begin_col_tags = "";
		$this->end_col_tags = "";
		$this->empty_col_tags = "";
		return $this;
	}
	// initializer for the new block
	function initBlock() {
		$this->tags = '';
		$this->initRow();
		return $this;
	}
	// initializer for the new row
	function initRow() {
		$this->cnt_cols = 0;
		$this->cols_buffer = array();
		return $this;
	}
	// check the current row is end (prepared for the next row)
	function checkRowEnd() {
		return ($this->cnt_cols == 0 && count($this->cols_buffer) == 0);
	}

	/*******************************************************************
		Methods to put column
	*******************************************************************/
	// put a column with htmlspecialchars() & nl2br()
	// col($str, $color='', $attrs=NULL, ...)
	function col() {
		$args = func_get_args();
		$str = array_shift($args);
		$str = $this->insBr($this->html($str));
		array_unshift($args, $str, FALSE);
		return call_user_func_array(array(&$this, 'putCol'), $args);
	}

	// put a strong column with htmlspecialchars() & nl2br()
	// sCol($str, $color='', $attrs=NULL, ...)
	function sCol() {
		$args = func_get_args();
		$str = array_shift($args);
		$str = $this->insBr($this->html($str));
		array_unshift($args, $str, TRUE);
		return call_user_func_array(array(&$this, 'putCol'), $args);
	}

	// put an empty column
	// eCol($attrs=NULL, ...)
	function eCol() {
		$args = func_get_args();
		array_unshift($args, $this->empty_col_tags, FALSE, '');
		return call_user_func_array(array(&$this, 'putCol'), $args);
	}

	// put a html tags column
	// hCol($str, $attrs=NULL, ...)
	function hCol() {
		$args = func_get_args();
		$str = array_shift($args);
		array_unshift($args, $str, FALSE, '');
		return call_user_func_array(array(&$this, 'putCol'), $args);
	}

	// put a column with htmlspecialchars() (without nl2br())
	// nCol($str, $color='', $attrs=NULL, ...)
	function nCol() {
		$args = func_get_args();
		$str = array_shift($args);
		$str = $this->html($str);
		array_unshift($args, $str, FALSE);
		return call_user_func_array(array(&$this, 'putCol'), $args);
	}

	// basic method
	function putCol($str, $strong=FALSE, $color='', $attrs=NULL, $colspan=1) {
		$this->cols_buffer[] = $this->makeColTags($str, $strong, $color, $attrs);
		$this->cnt_cols += $colspan;
		return $this;
	}

	/*******************************************************************
		Methods for end the current row (& prepare for the next row)
	*******************************************************************/
	// end the current row (& prepare for the next row)
	function nextRow($attrs=NULL) {
		$this->fillRow($attrs);
		$row = $this->makeBeginRowTags($attrs);
		$row .= implode('', $this->cols_buffer);
		$row .= $this->end_row_tags;
		$this->tags .= $row;
		$this->initRow();
		return $this;
	}

	// fill the lacked columns in the current row
	function fillRow($attrs=NULL) {
		while ($this->cnt_cols < $this->num_cols) {
			$this->eCol($attrs);
		}
		return $this;
	}

	/*******************************************************************
		generate tags
	*******************************************************************/
	// get the generated tags
	function get($attrs=NULL) {
		if (! $this->checkRowEnd()) {
			$this->nextRow();
		}
		$block = $this->makeBeginBlockTags($attrs);
		$block .= $this->tags;
		$block .= $this->end_block_tags;
		return $block;
	}

	// convert special characters to HTML entities
	function html($str) {
		return htmlspecialchars($str, ENT_COMPAT, $this->charset);
	}

	// inserts HTML line breaks before all newlines in a string
	function insBr($str) {
		return nl2br($str);
	}

	// make column tags
	function makeColTags($str, $strong=FALSE, $color='', $attrs=NULL) {
		if ($strong) {
			$str = '<strong>'.$str.'</strong>';
		}
		if ($color) {
			$str = '<font color="'.$color.'">'.$str.'</font>';
		}
		$col = $this->makeBeginColTags($attrs).$str.$this->end_col_tags;
		return $col;
	}

	// make the beginning tags of each column
	function makeBeginColTags($attrs=NULL) {
		return $this->addAttrs($this->begin_col_tags, $attrs);
	}

	// make the beginning tags of each row
	function makeBeginRowTags($attrs=NULL) {
		return $this->addAttrs($this->begin_row_tags, $attrs);
	}

	// make the beginning tags of each block
	function makeBeginBlockTags($attrs=NULL) {
		return $this->addAttrs($this->begin_block_tags, $attrs);
	}

	// insert attributes (string or array) into the tag
	function addAttrs($tags, $attrs=NULL) {
		if (is_null($attrs)) {
			return $tags;
		}
		if (is_string($attrs)) {
			return $this->insertAttrStr($tags, $attrs);
		}
		foreach ($attrs as $key => $val) {
			if (is_numeric($key)) {
				$tags = $this->insertAttrStr($tags, $val);
			} else {
				$attr_str = $key.'="'.$val.'"';
				$tags = $this->insertAttrStr($tags, $attr_str);
			}
		}
		return $tags;
	}

	// insert an attribute string into the tag
	function insertAttrStr($tags, $attr_str) {
		$ins_pos = -1;
		if (substr($tags, -2, 1) == '/') {
			$ins_pos = -2;
		}
		$body = substr($tags, 0, $ins_pos);
		$tail = substr($tags, $ins_pos);
		return $body.' '.$attr_str.$tail;
	}

	function pushAttrsStr(&$attrs, $attr_str) {
		if (empty($attrs)) {
			$attrs = $attr_str;
		} elseif (is_string($attrs)) {
			$attrs .= ' '.$attr_str;
		} else {
			$attrs[] = $attr_str;
		}
	}
}

/*======================================================================
	class HTMLtable
	Generator of table block (<table> ... </table>)
------------------------------------------------------------------------
	Usage example:
		$tag_gen = new HTMLtable(3, 'border="1"');
		$tag_gen->sCol('No.')->sCol('col1')->sCol('col2')->nextRow();
		for ($i=0; $i<count($rows); $i++) {
			$row = $rows[$i];
			$tag_gen->col($i+1)->col($row[0])->col($row[1])->nextRow();
		}
		echo $tag_gen->get();
------------------------------------------------------------------------
	Usual methods:
		col		put a column with htmlspecialchars() & nl2br()
		sCol	put a strong column with htmlspecialchars() & nl2br()
		eCol	put an empty column
		hCol	put a html tags column
		nCol	put a column with htmlspecialchars() (without nl2br())
		nextRow	end the current row (& prepare for the next row)
		get		get the generated tags
======================================================================*/
class HTMLtable extends HTMLblock {
	/*******************************************************************
		constructor & initializers
	*******************************************************************/
	// initialize the properties for table block
	function initSettings($num_cols, $block_attrs=NULL, $row_attrs=NULL, $col_attrs=NULL) {
		$this->num_cols = $num_cols;
		$this->begin_block_tags = $this->addAttrs("<table>", $block_attrs);
		$this->end_block_tags = "</table>\n";
		$this->begin_row_tags = $this->addAttrs("<tr>", $row_attrs);
		$this->end_row_tags = "</tr>\n";
		$this->begin_col_tags = $this->addAttrs("<td>", $col_attrs);
		$this->end_col_tags = "</td>";
		$this->empty_col_tags = "<br />";
		return $this;
	}

	/*******************************************************************
		Methods to put column
	*******************************************************************/
	// basic method
	function putCol($str, $strong=FALSE, $color='', $attrs=NULL, $colspan=1) {
		if ($colspan != 1) {
			$attr_str = 'colspan="'.$colspan.'"';
			$this->pushAttrsStr($attrs, $attr_str);
		}
		parent::putCol($str, $strong, $color, $attrs, $colspan);
		return $this;
	}

	/*******************************************************************
		Methods
	*******************************************************************/
	function beginHead($attrs=NULL) {
		$this->tags .= $this->addAttrs("<thead>", $attrs);
	}
	function endHead() {
		$this->tags .= "</thead>\n";
	}
	function beginBody($attrs=NULL) {
		$this->tags .= $this->addAttrs("<tbody>", $attrs);
	}
	function endBody() {
		$this->tags .= "</tbody>\n";
	}
	function beginFoot($attrs=NULL) {
		$this->tags .= $this->addAttrs("<tfoot>", $attrs);
	}
	function endFoot() {
		$this->tags .= "</tfoot>\n";
	}
}

?>
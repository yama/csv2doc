<?php
/**
 * Csv2Doc MODx module - Parameters setting utility
 *
 * @package Csv2Doc
 * @author Kazuyuki Ikeda (HIKIDAS Co.,Ltd)	
 * @link http://www.hikidas.com/
 * @version 0.9.2
 */

//======================================================================
class ParamContainer {
	var	$elements;

	function __construct() {
		$this->elements = array();
	}

	function add($name, &$element) {
		$this->elements[$name] =& $element;
	}

	function &element($name) {
		return $this->elements[$name];
	}

	function keys() {
		return array_keys($this->elements);
	}

	/*
		Usage:
		$params->set_configuration(compact($params->keys()));
	*/
	function set_configuration($var_array) {
		foreach ($var_array as $name => $val) {
			$this->elements[$name]->setVal($val);
		}
	}

	function get_request($names_str='', $request_method='') {
		switch (strtoupper($request_method)) {
		case 'POST':
			$request =& $_POST;
			break;
		case 'GET':
			$request =& $_GET;
			break;
		case '':
			$request =& $_REQUEST;
			break;
		default:
			return;
		}
		$names = explode(',', $names_str);
		foreach ($names as $name) {
			if (isset($request[$name]) && isset($this->elements[$name])) {
				$this->elements[$name]->setVal($request[$name]);
			}
		}
		return $names;
	}
}
//======================================================================
class tParam {
	var	$title;
	var	$val;
	var	$opt_list;
	function __construct($title='', $val='') {
		$this->title = $title;
		$this->val = $val;
	}
	function setVal($val) {
		$this->val = $val;
	}
	function getVal() {
		return $this->val;
	}
	function getTitle() {
		return $this->title;
	}
	function inputTag($name) {
		$tag = '<input type="text" class="inputBox" name="'.$name.'" value="'.$this->val.'" />';
		return $tag;
	}
	function hiddenTag($name) {
		$tag = '<input type="hidden" name="'.$name.'" value="'.$this->val.'" />';
		return $tag;
	}
	function setOptList(&$opt_list) {
		$this->opt_list =& $opt_list;
	}
}

//======================================================================
//	flag
class fParam extends tParam {
	function __construct($title='', $val='') {
		parent::__construct($title, $val);
		$this->opt_list = array(
			'Yes'	=>	1,
			'No'	=>	0,
		);
	}
	function setVal($val) {
		global $e;
		$val = (string)$val;
		if ($val != '0' && $val != '1') {
			putMsg("MSG_ERROR", $this->title.' is required only 0 or 1');
		} else {
			$this->val = (int)$val;
		}
	}
	function inputTag($name) {
		$tag = '';
		foreach ($this->opt_list as $opt => $val) {
			$sel = '';
			if ($this->val == $val) {
				$sel = ' checked';
			}
			if (is_numeric($opt)) {
				$tag .= '<input type="radio" name="'.$name.'" value="'.$val.'"'.$sel.'>'.$val."\n";
			} else {
				$tag .= '<input type="radio" name="'.$name.'" value="'.$val.'"'.$sel.'>'.$opt."\n";
			}
		}
		return $tag;
	}
}

//======================================================================
//	number
class nParam extends tParam {
	function setVal($val) {
		global $e;
		if (! is_numeric($val)) {
			putMsg("MSG_ERROR", $this->title.' is required numeric');
		} else {
			$this->val = (int)$val;
		}
	}
}

//======================================================================
//	select
class sParam extends tParam {
	function setVal($val) {
		global $e;
		if (! in_array($val, array_values($this->opt_list))) {
			putMsg("MSG_ERROR", 'You select not exist option for'.$this->title);
		} else {
			$this->val = $val;
		}
	}
	function inputTag($name) {
		$tag = '<select name="'.$name.'">'."\n";
		foreach ($this->opt_list as $opt => $val) {
			$sel = '';
			if ($this->val == $val) {
				$sel = ' selected';
			}
			if (is_numeric($opt)) {
				$tag .= '<option'.$sel.'>'.$val.'</option>'."\n";
			} else {
				$tag .= '<option value="'.$val.'"'.$sel.'>'.$opt.'</option>'."\n";
			}
		}
		$tag .= '</select>';
		return $tag;
	}
}
?>
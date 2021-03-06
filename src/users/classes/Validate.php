<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class Validate{
	private $_passed = false,
			$_errors = array(),
			$_db = null,
			$_ruleList = array();

	public function __construct($rules=null) {
		$this->_db = DB::getInstance();
		if ($rules) {
			$this->_ruleList = $this->stdRules($rules);
		}
	}

	public function stdRules($rules) {
		$newRuleList = array();
		foreach ($rules as $rulename => $rule) {
			$newrule = [];
			if (is_numeric($rulename) && !is_array($rule))
				$rulename = $rule; // shorthand
			$query = $this->_db->query("SELECT * FROM validate_rules WHERE name = ?", [$rulename]);
			$results = $query->first();
			foreach (['display', 'required', 'max', 'min',
								'unique'=>'unique_in_table', 'matches'=>'match_field',
								'update_id', 'is_numeric', 'valid_email', 'regex',
								'regex_display'] as $k => $rn) {
				if (is_numeric($k)) $k = $rn;
#var_dump($results);
#echo "rn=$rn<br />\n";
				if ($k == 'unique' && isset($rule['action'])) {
					$k = $k.'_'.$rule['action']; // 'unique'=='unique_add' or 'unique_update'
				}
				if (isset($rule[$k])) {
					if ($rule[$k] != 'unset') // special value to avoid getting DB validation rule
						$newrule[$k] = $rule[$k];
				} elseif (isset($results->$rn)) {
					$newrule[$k] = $results->$rn;
				}
			}
			if (isset($rule['alias'])) $rulename = $rule['alias'];
			$newRuleList[$rulename] = $newrule;
		}
		return $newRuleList;
	}

	public function describe($fields=array(), $ruleList=array(), $rulesToDescribe=array()) {
		$rtn = array();
		if (!$ruleList) $ruleList = $this->_ruleList;
		if (!$fields) $fields = array_keys($ruleList);
		foreach ((array)$fields as $f) {
			#echo "DEBUG: f=$f<br />\n";
			if (isset($ruleList[$f])) {
				foreach ((array)$ruleList[$f] as $k => $r) {
					#echo "DEBUG: k=$k<br />\n";
					switch ($k) {
						case 'min':
							$rtn[] = 'Min '.$r.' character'.($r>1?'s':'').' ';
							break;
						case 'max':
							$rtn[] = 'Max '.$r.' characters ';
							break;
						case 'required':
							if ($r) $rtn[] = 'Required ';
							else $rtn[] = 'Optional ';
							break;
						case 'unique':
						case 'unique_add':
						case 'unique_update':
							$rtn[] = 'Must be Unique in the database ';
							break;
						case 'is_numeric':
							$rtn[] = 'Must be a Numeric value ';
							break;
						case 'valid_email':
							$rtn[] = 'Must be formatted as a Valid email ';
							break;
						case 'regex':
							$rtn[] = $ruleList[$f]['regex_display'].' ';
							break;
						case 'matches':
						case 'match_field':
							$rtn[] = 'Must match '.$ruleList[$r]['display'].' ';
							break;
					}
				}
			}
		}
		return implode($rtn, ' &nbsp;-&nbsp; ');
	}

	public function check($source, $items = array()) {
		$this->_errors = [];
		if (!$items && $this->_ruleList) $items = $this->_ruleList;
		#var_dump($items);
		foreach ($items as $item => $rules) {
			$item = sanitize($item);
			$display = $rules['display'];
			foreach ($rules as $rule => $rule_value) {
				$value = trim($source[$item]);
				$value = sanitize($value);

				if (in_array($rule, ['display','regex_display','alias', 'update_id']))
					continue; // these aren't really "rules" per se
				if ($rule === 'required' && $rule_value && empty($value)) {
					$this->addError(["{$display} is required",$item]);
				} elseif (!empty($value)) {
					switch ($rule) {
						case 'min':
							if (strlen($value) < $rule_value) {
								$this->addError(["{$display} must be a minimum of {$rule_value} characters.",$item]);
							}
							break;

						case 'max':
							if (strlen($value) > $rule_value) {
								$this->addError(["{$display} must be a maximum of {$rule_value} characters.",$item]);
							}
							break;

						case 'matches':
							if ($value != $source[$rule_value]) {
								$match = $items[$rule_value]['display'];
								$this->addError(["{$match} and {$display} must match.",$item]);
							}
							break;

						case 'unique':
						case 'unique_add':
							$check = $this->_db->get($rule_value, array($item, '=', $value));
							if ($check->count()) {
								$this->addError(["{$display} already exists. Please choose another {$display}.",$item]);
							}
							break;

						case 'unique_update':
							if (isset($rules['update_id'])) {
								$table = $rule_value;
								$id = $rules['update_id'];
							} else
								list($table, $id) = explode(',', $rule_value);
							$query = "SELECT * FROM {$table} WHERE id != {$id} AND {$item} = '{$value}'";
							$check = $this->_db->query($query);
							if ($check->count()) {
								$this->addError(["{$display} already exists. Please choose another {$display}.",$item]);
							}
							break;

						case 'regex':
							if (!preg_match($rule_value, $value)) {
								$regex_display = $rules['regex_display'];
								$this->addError(["{$display} must match '$regex_display'. Please try again.",$item]);
							}
							break;

						case 'is_numeric':
							if (!is_numeric($value)) {
								$this->addError(["{$display} has to be a number. Please use a numeric value.",$item]);
							}
							break;

						case 'valid_email':
							if (!filter_var($value,FILTER_VALIDATE_EMAIL)) {
								$this->addError(["{$display} must be a valid email address.",$item]);
							}
							break;
					}
				}
			}

		}

		if (empty($this->_errors)) {
			$this->_passed = true;
		}
		return $this;
	}

	public function addError($error) {
		$this->_errors[] = $error;
		if (empty($this->_errors)) {
			$this->_passed = true;
		}else{
			$this->_passed = false;
		}
	}

	public function display_errors() {
		$html = '<ul class="bg-danger">';
		foreach($this->_errors as $error) {
			if (is_array($error)) {
				$html .= '<li class="text-danger">'.$error[0].'</li>';
				$html .= '<script>jQuery("document").ready(function() {jQuery("#'.$error[1].'").parent().closest("div").addClass("has-error");});</script>';
			}else{
				$html .= '<li class="text-danger">'.$error.'</li>';
			}
		}
		$html .= '</ul>';
		return $html;
	}

	public function stackErrorMessages($errs=array()) {
		foreach ($this->_errors as $err)
			$errs[] = $err[0];
		return $errs;
	}

	public function errors() {
		return $this->_errors;
	}

	public function passed() {
		return $this->_passed;
	}
}

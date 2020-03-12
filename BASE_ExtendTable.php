<?php
require_once ('../ClassesBase/MysqlTable.php');

class "CLASS"__ACTION extends ExtdEnum
{
	const __default = self::ADD;
	const ADD =		'add"class"';
	const EDIT =	'modif"class"';
	const DELETE =	'suppr"class"';

	const LEGEND = [	self::ADD => "...champs __Field|Default__ autorisés",
						self::EDIT => "...champs __Field|Default__ autorisés",
						self::DELETE => "...champs __Field|Default__ autorisés"
					];
}

// if necessary, extended CLASS INTERFACE
interface "Class" extends Table {
	// ----------- Specific Methods ------------
	// GETTERS
	// SETTERS
}

// if necessary, extended UI INTERFACE
interface UI_"Class" extends UI_Table {
	// ----------- Specific Methods ------------
}

// ---------- FINAL CLASS ------------
final class "Class"Table extends MysqlTable implements "Class"
{
	// Constants
	// Properties
	public "Parent"

	// Constructor
	function __construct ($read_write = ACCESS::__default) {
		// Define Parent first
		$this->Parent = new "ParentTable" ($read_write);
		if (headers_sent()) return false;
		$this->"Parent" = &$this->Parent;
		
		if (parent::_constructInit('TABLE', ['ChildTable'], 'ORDERING', 'LIMITING') !== true)
			return false;
		
		// Init rights
		$this->rights[ACCESS::READ] = "READ ACCESS";
		$this->rights[ACCESS::WRITE] = "WRITE ACCESS";
		// Init fields
		$this->set_field('id', TYPE::ID, 'Name', 0);
		// for child's class, id = NULL means we are working on parent, id = 0 if we are working on child
		$this->set_field('id_parent', TYPE::PARENT);
		$this->set_field('Field', "TYPE", 'name in print_errors functions', "default value", "required", "unique");
		// Init prefs
		$this->set_pref ("PREF::USED");
		
		return parent::_constructExit($read_write, "LOAD_GET_VARIABLE_NAME (=idload)");
	}
	
	// Destructor (if parent)
	function __destruct () {
		$this->Parent = NULL;
	}
	
	// --------- Override or Initialize Table methods ---------
	// GETTERS
	// SETTERS
	public function send_form () {
		if (!isset($_POST['action'])) return false;
		
		if ("CLASS"_ACTION::hasValue($_POST['action'])) {
			if ($_POST['action'] == "CLASS"_ACTION::DELETE)
				return $this->delete_entry ($_POST['id']);
			
			else return $this->_send_form ();
		}
		elseif ($this->Parent !== NULL) return $this->Parent->send_form();
		else return false;
	}
	
	// ----------- Specific Methods ------------
	// GETTERS
	// SETTERS
}

// ---------- FINAL UI CLASS ------------
final class UI_"Class"Table extends UI_MysqlTable implements UI_"Class"
{
	// Constants
	const CHOIX_LISTE = [10, 15, 20, 30];	// choix nb entrées par liste

	// --------- Override or Initialize UI methods ---------	
	// Draw specific form's fieldset
	public static function draw_fieldset($action, $data, $table) {
		if (!"CLASS"_ACTION::hasValue($action)) return false;
		if ($action == "CLASS"_ACTION::DELETE)
			return self::_draw_delete_form ($action, $data, $table, ERR::replaceFields("CLASS"_ACTION::LEGEND["CLASS"_ACTION::DELETE], $data));
		
		// FORMULAIRE
		echo '<form action="PAGE.PHP" method="post">';
			echo '<fieldset>';
				echo '<legend id="formTitle">';
				echo SQL_ERR::replaceFields("CLASS"_ACTION::LEGEND[$action], $data);
				echo '</legend>';
			
				echo '<input type="hidden" name="action" value="'.$action.'" required />';
				echo '<input type="hidden" name="id" id="id" value="'.$data['id'].'" required /></fieldset>';
				echo '<input type="reset" value="Revenir" onclick="loadContent(0, \''.$table.'\');" /><input type="submit" value="Valider" />';
		echo '</form>';
	}
	
	// Admin list
	// Draw list
	public static function draw_list ($list, $deploy = true, $deleteButtons = false) {
		self::_draw_list_header ($list['listData'], sizeof($list)-1, $deploy);
		self::_draw_list_block ("CLASS"_ACTION::DELETE, $list, $deleteButtons);
		self::_draw_list_nav ($list['listData'], sizeof($list)-1, $deploy);
	}
	
	// ----------- Specific Methods ------------
}
?>

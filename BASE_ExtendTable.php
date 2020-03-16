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
final class "Class"Table extends MysqlTable implements "Class", UI_"Class"
{
	// Constants
	// Properties
	public "Parent"

	// Constructor
	function __construct ($read_write = ACCESS::__default) {
		// Define Parent first
		$this->Parent = new "ParentTable" ($read_write);
		if (!$this->Parent) return false;
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

	
// ---------- FINAL UI CLASS ------------
	// Properties
	public static $choixListe = [10, 15, 20, 30];	// choix nb entrées par liste si modifié

	// --------- Override or Initialize UI methods ---------	
	// Draw specific form's fieldset
	public function draw_fieldset($action = NULL, $data = NULL) {
		if (is_null($action))
			$action = ($this->idLoad > 0 ? "CLASS"_ACTION::ADD : "CLASS"_ACTION::EDIT);
		if (is_null($data)) $data = $this->get_data();
		if (!"CLASS"_ACTION::hasValue($action)) return false;
		if ($action == "CLASS"_ACTION::DELETE)
			return $this->draw_delete_form ($data);
		
		// FORMULAIRE
		echo '<form action="PAGE.PHP" method="post">';
			echo '<fieldset>';
				echo '<legend id="formTitle">';
				echo SQL_ERR::replaceFields("CLASS"_ACTION::LEGEND[$action], $data);
				echo '</legend>';
			
				echo '<input type="hidden" name="action" value="'.$action.'" required />';
		echo '<input type="hidden" name="'.$this->getIdItem().'" id="id" value="'.$data[$this->getIdItem()].'" required /></fieldset>';
				echo '<input type="reset" value="Revenir" onclick="loadContent(0, \''.$this->table.'\');" /><input type="submit" value="Valider" />';
		echo '</form>';
		return true;
	}
	
	// Draw list
	public function draw_list ($list = NULL, $deploy = true, $read_write = NULL, $table = NULL) {
		if (is_null($read_write)) $read_write = $this->default_access;
		if (!ACCESS::hasKey($read_write)) return false;
		if (is_null($list))
			$list = $this->get_data(GET::LIST);
		if (!$list) return false;

		$this->_draw_list_header ($list['listData'], sizeof($list)-1, $deploy);
		$this->_draw_list_block ("CLASS"_ACTION::DELETE, $list, $read_write, $table);
		$this->_draw_list_nav ($list['listData'], sizeof($list)-1, $deploy);
		return true;
	}
	
	// PRIVATE
	// Draw specific delete form
	private function draw_delete_form ($data = NULL) {
		if (is_null($data)) $data = $this->get_data();
		if (!$data) return false;
		return $this->_draw_delete_form ("CLASS"_ACTION::DELETE, $data, ERR::replaceFields("CLASS"_ACTION::LEGEND["CLASS"_ACTION::DELETE], $data));
	}
	
	// ----------- Specific Methods ------------
}
?>

<?php
require_once ('UidayState.php');
require_once ('ClientsTable.php');

// ---------- FINAL CLASS ------------
final class OpeningsTable extends MysqlTable
{
	// Properties
	public $action = OPEN_ACTION::__default;
	protected $Client;
	
	// Constructor
	function __construct () {
		if (parent::__constructInit('agenda_ouvertures', 'Heure_Open ASC, Heure_Close ASC') !== true)
			return false;
		
		// Init consts
		include ('../Config/config.php');
		$horaires_habituels = DayState::get_default_openings($this->bdd, $prefixe.'agenda_ouvertures');
		$this->Defaults = array(
			"id" => 0,
			"id_client" => 0,
			"Date" => date('Y-m-d', time()),
			"Open_Close" => STATE::__default,
			"Heure_Open" => $horaires_habituels['Heure_Open'],
			"Heure_Close" => $horaires_habituels['Heure_Close']
		);
		// Init rights
		$this->rights[ACCESS::READ] = AUTHORISED::MMM;
		$this->rights[ACCESS::WRITE] = AUTHORISED::MMM;
		// Init fields
		$this->set_field('id', TYPE::ID, 'Date d\'ouverture __Date__');
		$this->set_field('id_client', TYPE::NUM, 'client', true);
		$this->set_field('Date', TYPE::DATE, 'date', true);
		$this->set_field('Open_Close', TYPE::NUM, 'statut', true);		
		$this->set_field('Heure_Open', TYPE::HOUR, 'heure d\'ouverture', true);		
		$this->set_field('Heure_Close', TYPE::HOUR, 'heure de fermeture', true);		
		$this->set_field('Nombre', TYPE::NUM, 'nombre de visiteurs');		
		$this->set_field('Message', TYPE::TEXT, 'message');	
		
		// Create Client fieldset
		$this->Client = new ClientsTable();
		
		return true;
	}
	
	// --------- Override or Initialize Table methods ---------
	// GETTERS
	// Return data array from DB
	public function get_data ($id = -1, $fields = "") {
		// Search for date data
		if (!is_numeric($id)) {
			// si l'id est une date, field est le numéro de l'entrée que l'on veut, classées du plus tôt au plus tard
			if ($this->rights_control(ACCESS::READ) !== true) return false;
			if (parent::isValidValue("Date", $id) !== true) return false;
			if (!is_numeric($field)) $field = 0;
			$reponse = $this->bdd->prepare('SELECT * FROM '.$this->Table.' WHERE Date = :date'.$this->getOrdering().' LIMIT 1 OFFSET :offset');
			$reponse->bindParam('date', $id, PDO::PARAM_STR);
			$reponse->bindParam('offset', $field, PDO::PARAM_INT);
			$reponse->execute();
			$donnees = $reponse->fetch();
			$reponse->closeCursor();
			if ($donnees) $donnees = $this->secure_data($donnees);
			else $donnees = $this->Defaults;
		}
		// search for id
		$donnees = parent::get_data ($id, $field);
				
		// Add client informations
		if ($field == "" && $donnees) {
			if ($this->Client->is_data($donnees['id_client']))
				$donnees['Client'] = $this->Client->get_data ($donnees['id_client']);
			else $donnees['Client']['id'] = $this->Client->getDefaults('id');
			unset ($donnees['id_client']);
		}
		
		return $donnees;
	}
	
	// Draw specific form's fieldset
	public function draw_fieldset($id = 0, $open_deb = 0, $open_fin = 0) {
		if ($this->rights_control(ACCESS::READ, $id) !== true) return false;
		
		// load from DB or get defaults		
		$champ = $this->get_data ($id);
		if (!is_numeric($id)) $champ['Date'] = $id;
		
		if ($id == 0) {
			$open_deb = ($open_deb == 0) ? $this->Defaults['Heure_Open'] : $open_deb;
			$open_fin = ($open_fin == 0) ? $this->Defaults['Heure_Close'] : $open_fin;
			if ($this->isValidValue('Heure_Open', $open_deb) === true)
				$champ['Heure_Open'] = substr($open_deb, 0, 5);
			if ($this->isValidValue('Heure_Close', $open_fin) === true)
				$champ['Heure_Close'] = substr($open_fin, 0, 5);
		}
		elseif ($this->action == OPEN_ACTION::CONFIRM) {
			$champ['Open_Close'] = STATE::OPTION;
			$minut = substr($champ['Heure_Open'], 3, 2);
			$hours = substr($champ['Heure_Open'], 0, 2);
			$minut += 30;
			if ($minut > 59) {
				$minut -= 60;
				if ($minut < 10) $minut = '0'.$minut;
				$hours += 2;
			}
			else $hours += 1;
			$champ['Heure_Close'] = $hours.':'.$minut;
		}

		
		// FORMULAIRE	
		echo '<legend>'.$this->replaceFields(OPEN_ACTION::LEGEND[$this->action], $champ['id']).'</legend>';
		echo '<input type="hidden" name="action" value="'.$this->action.'" /><br/>';
		echo '<input type="hidden" name="idopen" id="idopen" value="'.$champ['id'].'" /><br/>';
		echo '<input type="date" name="date" id="dateopen" placeholder="Date" value="'.$champ['Date'].'" title="Date d\'ouverture" /><br />';
		
		// Client
		echo '<ul>';
			echo '<li><input type="radio" name="client" id="public" value="0" ';
			if ($champ['Client']['id'] == 0) echo 'checked ';
			echo '/><label for="public">Publique</label></li>';
			echo '<li><input type="radio" name="client" id="private" value=';
			if ($champ['Client']['id'] > 0) echo '"'.$champ['Client']['id'].'" checked ';
			else echo '"-1" ';
			echo '/><label for="private">Privée</label></li>';
		echo '</ul>';
		
		echo '<div id="client"';
		if ($champ['Client']['id'] > 0) echo ' class="shown"';
		else echo ' class="hidden"';
		echo '>';
			$this->Client->draw_fieldset ($champ['Client']['id'], $this->action);
		echo '</div>';
			
		// Ajout/Suppression de créneaux
		if ($this->action == OPEN_ACTION::OPEN) {
			echo '<a ';
			if ($champ['Open_Close'] == STATE::CLOSED) echo ' class="hidden"';
			else echo ' class="shown"';
			echo ' title="Ajouter un crénau horaire">';
			  echo '<img onclick="addDelSlot(1); return false;" id="addbutton" class="imgButton" src="../Styles/add.png" alt="+" onmousedown="getElementById(\'addbutton\').src=\'../Styles/add-clic.png\';" onmouseup="getElementById(\'addbutton\').src=\'../Styles/add.png\';" onmouseout="getElementById(\'addbutton\').src=\'../Styles/add.png\';">';
			echo '</a><br />';
			
			echo '<a ';
			if ($id >= 0) echo ' class="hidden"';
			else echo ' class="shown"';
			echo ' title="Retirer le crénau horaire">';
			  echo '<img onclick="addDelSlot(0); return false;" id="delbutton" class="imgButton" src="../Styles/remove.png" alt="-" onmousedown="getElementById(\'delbutton\').src=\'../Styles/remove-clic.png\';" onmouseup="getElementById(\'delbutton\').src=\'../Styles/remove.png\';" onmouseout="getElementById(\'delbutton\').src=\'../Styles/remove.png\';">';
			echo '</a>';
		}
	
		// Aperçu
		echo '<div id="apercuHoraires" class="';
		if ($champ['Open_Close'] == STATE::CLOSED) echo 'hidden';
		else echo 'shown';
		echo '">';
			include('../Administration/afficheHoraires.php');
		echo '</div>';
		
		// Ouverture
		echo '<ul>';
			echo '<li';
			if ($this->action == OPEN_ACTION::CONFIRM) echo ' class="hidden"';
			echo '><input type="radio" name="openclose" id="closed" value="'.STATE::CLOSED.'" onchange="afficheHoraires();" ';
			if (($champ['Open_Close'] & ~STATE::FULL) == 0) echo 'checked ';
			echo '/><label for="closed">Fermé</label></li>';
			echo '<li';
			if ($this->action == OPEN_ACTION::CONFIRM || $champ['Open_Close'] == STATE::CLOSED) echo ' class="hidden"';
			else echo ' class="shown"';
			echo '><input type="radio" name="openclose" id="option" value="'.STATE::OPTION.'" onchange="afficheHoraires();" ';
			if (($champ['Open_Close'] & ~STATE::FULL) == STATE::OPTION) echo 'checked ';
			echo '/><label for="option">Option</label></li>';
			echo '<li';
			if ($this->action == OPEN_ACTION::CONFIRM || $champ['Open_Close'] == STATE::CLOSED) echo ' class="hidden"';
			else echo ' class="shown"';
			echo '><input type="radio" name="openclose" id="booked" value="'.STATE::BOOKED.'" onchange="afficheHoraires();" ';
			if (($champ['Open_Close'] & ~STATE::FULL) == STATE::BOOKED) echo 'checked ';
			echo '/><label for="booked">Réservé</label></li>';
			echo '<li';
			if ($this->action == OPEN_ACTION::CONFIRM) echo ' class="hidden"';
			echo '><input type="radio" name="openclose" id="opened" value="'.STATE::OPENED.'" onchange="afficheHoraires();" ';
			if (($champ['Open_Close'] & ~STATE::FULL) == STATE::OPENED) echo 'checked ';
			echo '/><label for="opened">Ouvert</label></li>';
			echo '<li';
			if ($this->action == OPEN_ACTION::CONFIRM || $champ['Open_Close'] == STATE::CLOSED) echo ' class="hidden"';
			else echo ' class="shown"';
			echo '><input type="checkbox" name="full" id="full" value="'.STATE::FULL.'" onchange="afficheHoraires();" ';
			if ($champ['Open_Close'] & STATE::FULL) echo 'checked ';
			echo '/><label for="full">Complet</label></li>';
		echo '</ul>';
		
		// Horaires
		echo '<div id="horaires"';
		if ($champ['Open_Close'] == STATE::CLOSED) echo ' class="hidden"';
		else echo ' class="shown"';
		echo '>';
			echo '<input type="time" name="open" id="open" placeholder="Ouverture" value="'.$champ['Heure_Open'].'" title="Heure d\'ouverture" onchange="afficheHoraires(1);" /><br />';
			echo '<input type="time" name="close" id="close" placeholder="Fermeture" value="'.$champ['Heure_Close'].'" title="Heure de fermeture" onchange="afficheHoraires(2);" />';
		echo '</div>';
		if ($this->action == OPEN_ACTION::CONFIRM) {
			echo '<input type="submit" name="reject" value="Rejeter" onclick="checkAndSend(\'reject\'); return false;" />';
			echo '<input type="submit" name="confirm" value="Accepter" onclick="checkAndSend(\'confirm\'); return false;"  />';
		}
		else echo '<input type="submit" name="confirm" value="Valider" onclick="checkAndSend(\'dates\'); return false;"  />';
	}
	
	// SETTERS
	public function send_form () {
		if (!isset($_POST['action']) || !OPEN_ACTION::hasKey($_POST['action'])) return false;
		if ($_POST['action'] == OPEN_ACTION::DELETE)
			return $this->delete_entry ($_POST['id']);
		
		if ($this->Client->_send_form ($_POST) !== false)
			return $this->_send_form ($_POST);
		else return false;
	}
		
	// ----------- Specific Methods ------------
	// GETTERS
	public function get_first ($date) {
		return $this->get_data ($date, 0, 1);
	}
		
	// SETTERS
	protected function delete_entry ($id) {
		if (!$this->is_data($id)) return false;
		if (!$this->rights_control(ACCESS::WRITE)) return false;

		$clientid = $this->get_data ($id, $id_client);
		if (parent::delete_entry ($id) !== true) return false;
		
		// delete client if no more entry for this one
		$reponse = $this->bdd->prepare ('SELECT COUNT(*) AS nb_entries FROM '.$this->Table.' WHERE id_client = :clientid');
		$reponse->bindParam('clientid', $clientid, PDO::PARAM_INT);
		$reponse->execute();
		$data= $reponse->fetch();
		$reponse->closeCursor();
		
		if ($data['nb_entries'] > 0)
			return $this->Client->delete_entry ($clientid);
		else return true;
	}

}
?>

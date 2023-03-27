<?php
require_once ('MysqlTable.php');

// UI_PublicTable implements public formatting
interface UI_PublicTable extends UI_Table {
	// ----------- Specific Methods ------------
	// GETTERS
	// Draw BBCode modifiers tools in Admin section
	public static function draw_bbModifiers();
	// Draw BBLive checkbox
	public static function draw_bbCheckbox();
	// Draw public page
	public function draw_page ($id = NULL); // $id = false to load data from $_POST (bbTesting)
	// SETTERS
}

abstract class PublicTable extends MysqlTable implements UI_PublicTable {
	// ----------- Specific Methods ------------
	// GETTERS
	// Draw BBCode modifiers tools in Admin section
	public static function draw_bbModifiers() {
		?><!--<ul>
			<li>[b]...[/b] : <b>gras</b></li>
			<li>[u]...[/u] : <u>souligné</u></li>
			<li>[i]...[/i] : <i>italique</i></li>
			<li>[p] : nouveau paragraphe</li>
		</ul>-->
		<ul class="bbEdit">
			<li onclick="document.bbCode.formatText('title')" title="Titre : [H]...[/H]"><span style="font-family: Titres, Ubuntu, 'Liberation Sans', Arial, FreeSans, sans-serif;">Titre</span></li>
			<li onclick="document.bbCode.formatText('bold')" title="Gras : [b]...[/b]"><b>B</b></li>
			<li onclick="document.bbCode.formatText('underline')" title="Souligné : [u]...[/u]"><u>U</u></li>
			<li onclick="document.bbCode.formatText('italic')" title="Italique : [i]...[/i]"><i>I</i></li>
			<li onclick="document.bbCode.formatText('paragraph')" title="Nouveau paragraphe : ...[P]...">§</li>
			<li onclick="document.bbCode.formatText('url')" title="Lien : [url=http://...]...[/url]"><span style="color: #0e6013">url</span></li>
		</ul><?php
	}
	
	// Draw BBLive checkbox
	public static function draw_bbCheckbox() {
		$prefApercu = (isset($_COOKIE[PREFS::APERCU_BB['name']]) ? $_COOKIE[PREFS::APERCU_BB['name']] : PREFS::APERCU_BB['default']);
echo '<label for="apercu">Aperçu en temps réel</label><input type="checkbox" name="bbLive" id="bbLive" onclick="document.bbCode.printBbCode(true);"';
if ($prefApercu) echo ' checked';
echo ' /><br />';
	}
	
	// PROTECTED
	protected function _draw_page ($id = NULL) {
		if (!$id) {
			$data = $this->secure_data($_POST);
			// test if data found (break on first value != 0
			$i = 0;
			foreach ($data as $key => $value) {
				if ($key == 'type') continue;
				elseif ($value) { $i++; break; }
			}
			if (!$i) return false;
		}
		else $data = $this->get_data($id, GET::ALL);
		
		return $data;
	}
}
?>

<?php
require_once ('Generic.php');

class FILE_ERR extends ERR {




}

class FileManagement {

	const MAX_UPLOAD = 2000000;	// 2Mo
	
	// Format de fichiers acceptés
	const TYPE_UPLOAD = array ('jpg','jpeg','png','gif');
	// Chemin vers fichiers finaux
	const PATH_UPLOAD = array (
		'tmp'		=> '../Administration/tmp/',
		'asso'		=> '../Animations/Files/',
		'event'		=> '../Agenda/Files/',
		'thumbs'	=> '../Collection/Files/thumbs/',
		'galerie'	=> '../Collection/Files/masters/'
	);
	const FILIGRANE = '../Administration/Images/logofiligrane.png';
	
	// prevent instanciation
    function __construct() { }
	
	public static function draw_fieldset ($field, $image = "") {
		echo '<p>Vous pouvez choisir une image (jpg, png, gif) pour illustrer la page (maximum '.(self::MAX_UPLOAD/1000000).'Mo).</p>';
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="'.self::MAX_UPLOAD.'" />';
		echo '<div class="container">';
			echo '<img id="imageupload" ';
			if ($image != "") echo 'style="display: block;" ';
			echo 'src="'.$image.'" alt="" />';
			echo '<img id="loading" src="../Styles/loading.png" alt="chargement" />';
			echo '<input type="hidden" name="uploaded" id="uploaded" value="'.$image.'" />';
			echo '<img class="imgButton" id="deleteimg" ';
			if ($image == "") { echo 'style="visibility: hidden;" '; }
			echo 'alt="x" title="Supprimer cette image" src="../Styles/remove.png" onmousedown="this.src=\'../Styles/remove-clic.png\';" onmouseup="this.src=\'../Styles/remove.png\';" onmouseout="this.src=\'../Styles/remove.png\';" onclick="supprImage(\''.$image.'\');" ';
			if ($image != "") { echo 'style="visibility: visible;" '; }
			echo '/>';
			echo '<input type="file" id="file" name="'.$field.'" value="" title="Insérer une image" onchange="preloadFile();"><br />';
		echo '</div>';
	}
}
?>

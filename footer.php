<!--<script type="text/javascript">
	javascript:(function(){var s=document.createElement("script");s.onload=function(){bootlint.showLintReportForCurrentDocument([]);};s.src="https://maxcdn.bootstrapcdn.com/bootlint/latest/bootlint.min.js";document.body.appendChild(s)})();
</script>-->

</div> <!-- fin div row -->

<?php
/* Rajout legende pour affichage responsive */
	if (Settings::get("legend") == '0' && $grr_script_name != "edit_entry.php" && $grr_script_name != "year.php"){
		echo '<div class="container-fluid">
				<div class="legende-bas">';
		echo '	<h4>'.get_vocab("mg_legende").'</h4>';
		show_colour_key($area);
		echo '</div>';
		echo '</div>';
	}


	// Ajout pour le extended-uportal-footer
	echo '<extended-uportal-footer domain="'.$_SERVER['HTTP_HOST'].'"';
	echo '    template-api-path="/commun/portal_template_api.tpl.json"';
	echo '    links=\'[{"title":"Accessibilité : partiellement conforme"},{"title":"CGU","href":"/files/textes/droits_usage.html"},{"title":"Apereo.org","href":"https://www.apereo.org/"},{"title":"ESUP-Portail","href":"https://www.esup-portail.org/"}]\' >';
	echo '</extended-uportal-header>';
?>

</div>
</div>
</body>
</html>

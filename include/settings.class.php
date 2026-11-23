<?php
require_once __DIR__ . '/model/SettingsOverrideResolver.php';
require_once __DIR__ . '/model/EtablissementSettingsOverride.php';

class Settings {

	static $grrSettings;
	static ?SettingsOverrideResolver $override = null;

	public function __construct()
	{
		return self::load();
	}


	static function load()
	{
		if (self::$override === null) {
			self::$override = new EtablissementSettingsOverride();
		}
		if (self::$override && method_exists(self::$override, 'load')) {
			self::$override->load();
		}

		$test = grr_sql_query1("SELECT NAME FROM ".TABLE_PREFIX."_setting WHERE NAME='version'");
		if ($test != -1)
			$sql = "SELECT `NAME`, `VALUE` FROM ".TABLE_PREFIX."_setting";
		else
			$sql = "SELECT `NAME`, `VALUE` FROM setting";
		$res = grr_sql_query($sql);
		if (!$res)
			return false;
		if (grr_sql_count($res) == 0)
			return false;
		else
		{
			for ($i = 0; ($row = grr_sql_row($res, $i)); $i++)
				self::$grrSettings[$row[0]] = $row[1];
			return true;
		}
	}

    static function get($_name, $global = false)
    {
        if (!$global && self::$override) {
            $value = self::$override->resolve($_name);
            if ($value !== null)
                return $value;
        }

        if (isset(self::$grrSettings[$_name]))
            return self::$grrSettings[$_name];
    }

    static function getEtab($_name)
    {
        return self::get($_name);
    }

    /**
     * Lit un flag de module stocké en base sous forme de string "Oui"/"Non"
     * et le retourne en booléen.
     */
    static function getBool($_name, $global = false)
    {
        return self::get($_name, $global) == "Oui";
    }


    static function getGeneral($_name)
    {
        if (isset(self::$grrSettings[$_name]))
            return self::$grrSettings[$_name];
    }

	static function set($_name, $_value)
	{
		if (isset(self::$grrSettings[$_name]))
		{
			$sql = "UPDATE ".TABLE_PREFIX."_setting set VALUE = '" . protect_data_sql($_value) . "' where NAME = '" . protect_data_sql($_name) . "'";
			$res = grr_sql_query($sql);
			if (!$res)
				return false;
		}
		else
		{
			$sql = "INSERT INTO ".TABLE_PREFIX."_setting set NAME = '" . protect_data_sql($_name) . "', VALUE = '" . protect_data_sql($_value) . "'";
			$res = grr_sql_query($sql);
			if (!$res)
				return (false);
		}
		self::$grrSettings[$_name] = $_value;
		return true;
	}
	
	static function setEtab($_name, $_value)
	{
		if (self::$override instanceof EtablissementSettingsOverride) {
			return self::$override->set($_name, $_value);
		}
		return false;
	}

}
?>

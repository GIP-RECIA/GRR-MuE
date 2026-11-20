<?php

require_once __DIR__ . '/SettingsOverrideResolver.php';

class EtablissementSettingsOverride implements SettingsOverrideResolver
{
    private array $values = [];

    public function load(): void
    {
        $sql = "SELECT code_etab,`NAME`, `VALUE` FROM " . TABLE_PREFIX . "_setting_etablissement";
        $res = grr_sql_query($sql);
        if ($res && grr_sql_count($res) != 0) {
            for ($i = 0; ($row = grr_sql_row($res, $i)); $i++) {
                $this->values[$row[0]][$row[1]] = $row[2];
            }
        }
    }

    public function resolve(string $name): ?string
    {
        $etab = $_SESSION['current_etablisement'] ?? null;
        if ($etab !== null && isset($this->values[$etab][$name]) && $this->values[$etab][$name] !== '') {
            return $this->values[$etab][$name];
        }
        return null;
    }

    public function set(string $name, $value): bool
    {
        $codeEtab = getCodeEtablissementFromId(getIdEtablissementCourant());

        if (isset($this->values[$codeEtab][$name])) {
            $sql = "UPDATE " . TABLE_PREFIX . "_setting_etablissement set VALUE = '" . protect_data_sql($value) . "' where NAME = '" . protect_data_sql($name) . "' AND code_etab = '" . protect_data_sql($codeEtab) . "'";
        } else {
            $sql = "INSERT into " . TABLE_PREFIX . "_setting_etablissement set NAME = '" . protect_data_sql($name) . "', VALUE = '" . protect_data_sql($value) . "', code_etab = '" . protect_data_sql($codeEtab) . "'";
        }

        if (!grr_sql_query($sql)) {
            return false;
        }

        $this->values[$codeEtab][$name] = $value;
        return true;
    }
}
